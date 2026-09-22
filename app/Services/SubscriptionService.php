<?php

namespace App\Services;

use App\Mail\SubscriptionInvoiceMail;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Support\SubscriptionCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class SubscriptionService
{
    public function __construct(protected AuditLogger $audit)
    {
    }

    public function createForCompany(
        Company $company,
        SubscriptionPlan $plan,
        Carbon $startsAt,
        ?Carbon $expiresAt = null,
        string $status = SubscriptionCatalog::STATUS_ACTIVE,
        ?string $notes = null
    ): Subscription {
        $expiresAt = $expiresAt ?: $this->addPeriod($startsAt->copy(), $plan);

        return DB::transaction(function () use ($company, $plan, $startsAt, $expiresAt, $status, $notes) {
            $subscription = Subscription::query()->updateOrCreate(
                ['company_id' => $company->id],
                [
                    'subscription_plan_id' => $plan->id,
                    'status' => $status,
                    'starts_at' => $startsAt->toDateString(),
                    'expires_at' => $expiresAt->toDateString(),
                    'trial_ends_at' => $status === SubscriptionCatalog::STATUS_TRIAL ? $expiresAt : null,
                    'suspended_at' => null,
                    'cancelled_at' => null,
                    'notes' => $notes,
                ]
            );

            $action = $status === SubscriptionCatalog::STATUS_PENDING_APPROVAL
                ? SubscriptionCatalog::ACTION_REQUESTED
                : SubscriptionCatalog::ACTION_CREATED;

            $this->recordHistory($subscription, $action, [
                'new_plan_id' => $plan->id,
                'new_expires_at' => $expiresAt->toDateString(),
                'new_status' => $status,
                'notes' => $notes,
            ]);

            $this->audit->record('create', 'subscriptions', $company, null, [
                'plan' => $plan->name,
                'expires_at' => $expiresAt->toDateString(),
                'status' => $status,
            ]);

            return $subscription->fresh('plan');
        });
    }

    public function approveRequest(Subscription $subscription, ?string $notes = null): Subscription
    {
        if ($subscription->status !== SubscriptionCatalog::STATUS_PENDING_APPROVAL) {
            throw new InvalidArgumentException('Only pending subscription requests can be approved.');
        }

        $plan = $subscription->plan;
        if (! $plan) {
            throw new InvalidArgumentException('A subscription plan is required to approve this request.');
        }

        $starts = now()->startOfDay();
        $expires = $this->addPeriod($starts, $plan);
        $newStatus = $plan->slug === 'trial'
            ? SubscriptionCatalog::STATUS_TRIAL
            : SubscriptionCatalog::STATUS_ACTIVE;

        $subscription->fill([
            'status' => $newStatus,
            'starts_at' => $starts->toDateString(),
            'expires_at' => $expires->toDateString(),
            'trial_ends_at' => $newStatus === SubscriptionCatalog::STATUS_TRIAL ? $expires : null,
            'suspended_at' => null,
            'cancelled_at' => null,
            'notes' => $notes ?: $subscription->notes,
        ]);
        $subscription->save();

        $this->recordHistory($subscription, SubscriptionCatalog::ACTION_APPROVED, [
            'previous_plan_id' => $plan->id,
            'new_plan_id' => $plan->id,
            'previous_status' => SubscriptionCatalog::STATUS_PENDING_APPROVAL,
            'new_status' => $newStatus,
            'new_expires_at' => $expires->toDateString(),
            'notes' => $notes,
        ]);

        $this->audit->record('approve', 'subscriptions', $subscription->company, [
            'status' => SubscriptionCatalog::STATUS_PENDING_APPROVAL,
        ], [
            'status' => $newStatus,
            'expires_at' => $expires->toDateString(),
            'plan' => $plan->name,
        ]);

        return $subscription->fresh('plan');
    }

    public function rejectRequest(Subscription $subscription, ?string $notes = null): Subscription
    {
        if ($subscription->status !== SubscriptionCatalog::STATUS_PENDING_APPROVAL) {
            throw new InvalidArgumentException('Only pending subscription requests can be rejected.');
        }

        $subscription->update([
            'status' => SubscriptionCatalog::STATUS_REJECTED,
            'cancelled_at' => now(),
            'notes' => $notes ?: $subscription->notes,
        ]);

        $this->recordHistory($subscription, SubscriptionCatalog::ACTION_REJECTED, [
            'previous_plan_id' => $subscription->subscription_plan_id,
            'new_plan_id' => $subscription->subscription_plan_id,
            'previous_status' => SubscriptionCatalog::STATUS_PENDING_APPROVAL,
            'new_status' => SubscriptionCatalog::STATUS_REJECTED,
            'notes' => $notes,
        ]);

        $this->audit->record('reject', 'subscriptions', $subscription->company, [
            'status' => SubscriptionCatalog::STATUS_PENDING_APPROVAL,
        ], [
            'status' => SubscriptionCatalog::STATUS_REJECTED,
        ]);

        return $subscription->fresh('plan');
    }

    public function renew(Subscription $subscription, ?SubscriptionPlan $plan = null, ?Carbon $fromDate = null, ?string $notes = null): Subscription
    {
        $plan = $plan ?: $subscription->plan;
        if (! $plan) {
            throw new InvalidArgumentException('A subscription plan is required to renew.');
        }

        $now = now()->startOfDay();
        $currentExpiry = optional($subscription->expires_at)->copy()->startOfDay();
        $base = $fromDate ? $fromDate->copy()->startOfDay() : (
            ($currentExpiry && $currentExpiry->gte($now)) ? $currentExpiry : $now
        );
        $newExpiry = $this->addPeriod($base, $plan);
        $previousExpiry = optional($subscription->expires_at)->toDateString();
        $previousPlan = $subscription->subscription_plan_id;
        $previousStatus = $subscription->effectiveStatus();

        $subscription->fill([
            'subscription_plan_id' => $plan->id,
            'status' => $subscription->status === SubscriptionCatalog::STATUS_TRIAL
                ? SubscriptionCatalog::STATUS_TRIAL
                : SubscriptionCatalog::STATUS_ACTIVE,
            'expires_at' => $newExpiry->toDateString(),
            'suspended_at' => null,
            'cancelled_at' => null,
        ]);
        if (! $subscription->starts_at) {
            $subscription->starts_at = $now->toDateString();
        }
        $subscription->save();

        $this->recordHistory($subscription, SubscriptionCatalog::ACTION_RENEWED, [
            'previous_plan_id' => $previousPlan,
            'new_plan_id' => $plan->id,
            'previous_expires_at' => $previousExpiry,
            'new_expires_at' => $newExpiry->toDateString(),
            'previous_status' => $previousStatus,
            'new_status' => $subscription->effectiveStatus(),
            'notes' => $notes,
        ]);

        $this->audit->record('renew', 'subscriptions', $subscription->company, [
            'expires_at' => $previousExpiry,
        ], [
            'expires_at' => $newExpiry->toDateString(),
            'plan' => $plan->name,
        ]);

        return $subscription->fresh('plan');
    }

    public function extend(Subscription $subscription, int $days, ?string $notes = null): Subscription
    {
        if ($days < 1) {
            throw new InvalidArgumentException('Extension days must be at least 1.');
        }

        $now = now()->startOfDay();
        $currentExpiry = optional($subscription->expires_at)->copy()->startOfDay() ?: $now;
        $base = $currentExpiry->lt($now) ? $now : $currentExpiry;
        $newExpiry = $base->copy()->addDays($days);
        $previousExpiry = optional($subscription->expires_at)->toDateString();
        $previousStatus = $subscription->effectiveStatus();

        $subscription->update([
            'expires_at' => $newExpiry->toDateString(),
            'status' => $subscription->status === SubscriptionCatalog::STATUS_SUSPENDED
                ? SubscriptionCatalog::STATUS_SUSPENDED
                : SubscriptionCatalog::STATUS_ACTIVE,
            'cancelled_at' => null,
        ]);

        $this->recordHistory($subscription, SubscriptionCatalog::ACTION_EXTENDED, [
            'previous_plan_id' => $subscription->subscription_plan_id,
            'new_plan_id' => $subscription->subscription_plan_id,
            'previous_expires_at' => $previousExpiry,
            'new_expires_at' => $newExpiry->toDateString(),
            'previous_status' => $previousStatus,
            'new_status' => $subscription->fresh()->effectiveStatus(),
            'notes' => $notes ?: ('Extended by ' . $days . ' day(s)'),
        ]);

        $this->audit->record('extend', 'subscriptions', $subscription->company, [
            'expires_at' => $previousExpiry,
        ], [
            'expires_at' => $newExpiry->toDateString(),
            'days' => $days,
        ]);

        return $subscription->fresh('plan');
    }

    public function changePlan(Subscription $subscription, SubscriptionPlan $plan, ?string $notes = null): Subscription
    {
        $previousPlan = $subscription->subscription_plan_id;
        $previousExpiry = optional($subscription->expires_at)->toDateString();

        $subscription->update(['subscription_plan_id' => $plan->id]);

        $this->recordHistory($subscription, SubscriptionCatalog::ACTION_PLAN_CHANGED, [
            'previous_plan_id' => $previousPlan,
            'new_plan_id' => $plan->id,
            'previous_expires_at' => $previousExpiry,
            'new_expires_at' => $previousExpiry,
            'previous_status' => $subscription->effectiveStatus(),
            'new_status' => $subscription->effectiveStatus(),
            'notes' => $notes,
        ]);

        $this->audit->record('plan_changed', 'subscriptions', $subscription->company, [
            'plan_id' => $previousPlan,
        ], [
            'plan_id' => $plan->id,
            'plan' => $plan->name,
        ]);

        return $subscription->fresh('plan');
    }

    public function suspend(Subscription $subscription, ?string $notes = null): Subscription
    {
        $previous = $subscription->effectiveStatus();
        $subscription->update([
            'status' => SubscriptionCatalog::STATUS_SUSPENDED,
            'suspended_at' => now(),
        ]);

        $this->recordHistory($subscription, SubscriptionCatalog::ACTION_SUSPENDED, [
            'previous_plan_id' => $subscription->subscription_plan_id,
            'new_plan_id' => $subscription->subscription_plan_id,
            'previous_expires_at' => optional($subscription->expires_at)->toDateString(),
            'new_expires_at' => optional($subscription->expires_at)->toDateString(),
            'previous_status' => $previous,
            'new_status' => SubscriptionCatalog::STATUS_SUSPENDED,
            'notes' => $notes,
        ]);

        $this->audit->record('suspend', 'subscriptions', $subscription->company, ['status' => $previous], [
            'status' => SubscriptionCatalog::STATUS_SUSPENDED,
        ]);

        return $subscription->fresh('plan');
    }

    public function activate(Subscription $subscription, ?string $notes = null): Subscription
    {
        $previous = $subscription->effectiveStatus();
        $now = now()->startOfDay();
        $expires = optional($subscription->expires_at)->copy()->startOfDay();
        $status = ($expires && $expires->gte($now))
            ? SubscriptionCatalog::STATUS_ACTIVE
            : SubscriptionCatalog::STATUS_EXPIRED;

        $subscription->update([
            'status' => $status,
            'suspended_at' => null,
            'cancelled_at' => null,
        ]);

        $this->recordHistory($subscription, SubscriptionCatalog::ACTION_ACTIVATED, [
            'previous_plan_id' => $subscription->subscription_plan_id,
            'new_plan_id' => $subscription->subscription_plan_id,
            'previous_expires_at' => optional($subscription->expires_at)->toDateString(),
            'new_expires_at' => optional($subscription->expires_at)->toDateString(),
            'previous_status' => $previous,
            'new_status' => $subscription->fresh()->effectiveStatus(),
            'notes' => $notes,
        ]);

        $this->audit->record('activate', 'subscriptions', $subscription->company, ['status' => $previous], [
            'status' => $subscription->status,
        ]);

        return $subscription->fresh('plan');
    }

    public function cancel(Subscription $subscription, ?string $notes = null): Subscription
    {
        $previous = $subscription->effectiveStatus();
        $subscription->update([
            'status' => SubscriptionCatalog::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $this->recordHistory($subscription, SubscriptionCatalog::ACTION_CANCELLED, [
            'previous_plan_id' => $subscription->subscription_plan_id,
            'new_plan_id' => $subscription->subscription_plan_id,
            'previous_expires_at' => optional($subscription->expires_at)->toDateString(),
            'new_expires_at' => optional($subscription->expires_at)->toDateString(),
            'previous_status' => $previous,
            'new_status' => SubscriptionCatalog::STATUS_CANCELLED,
            'notes' => $notes,
        ]);

        $this->audit->record('cancel', 'subscriptions', $subscription->company, ['status' => $previous], [
            'status' => SubscriptionCatalog::STATUS_CANCELLED,
        ]);

        return $subscription->fresh('plan');
    }

    public function resetStatus(Subscription $subscription, ?string $notes = null): Subscription
    {
        $previous = $subscription->effectiveStatus();
        $now = now()->startOfDay();
        $expires = optional($subscription->expires_at)->copy()->startOfDay();
        $status = ($expires && $expires->gte($now))
            ? SubscriptionCatalog::STATUS_ACTIVE
            : SubscriptionCatalog::STATUS_EXPIRED;

        $subscription->update([
            'status' => $status,
            'suspended_at' => null,
            'cancelled_at' => null,
        ]);

        $this->recordHistory($subscription, SubscriptionCatalog::ACTION_RESET, [
            'previous_plan_id' => $subscription->subscription_plan_id,
            'new_plan_id' => $subscription->subscription_plan_id,
            'previous_expires_at' => optional($subscription->expires_at)->toDateString(),
            'new_expires_at' => optional($subscription->expires_at)->toDateString(),
            'previous_status' => $previous,
            'new_status' => $subscription->fresh()->effectiveStatus(),
            'notes' => $notes ?: 'Subscription status reset from dates.',
        ]);

        $this->audit->record('reset', 'subscriptions', $subscription->company, ['status' => $previous], [
            'status' => $subscription->status,
        ]);

        return $subscription->fresh('plan');
    }

    public function syncDerivedStatus(Subscription $subscription): Subscription
    {
        $stored = (string) $subscription->status;
        if (in_array($stored, [
            SubscriptionCatalog::STATUS_PENDING_APPROVAL,
            SubscriptionCatalog::STATUS_REJECTED,
            SubscriptionCatalog::STATUS_SUSPENDED,
            SubscriptionCatalog::STATUS_CANCELLED,
            SubscriptionCatalog::STATUS_DEACTIVATED,
        ], true)) {
            return $subscription;
        }

        $effective = $subscription->effectiveStatus();
        $persist = $effective === SubscriptionCatalog::STATUS_EXPIRING_SOON
            ? ($stored === SubscriptionCatalog::STATUS_TRIAL ? SubscriptionCatalog::STATUS_TRIAL : SubscriptionCatalog::STATUS_ACTIVE)
            : $effective;

        if ($persist === SubscriptionCatalog::STATUS_EXPIRED && $stored !== SubscriptionCatalog::STATUS_EXPIRED) {
            $subscription->update(['status' => SubscriptionCatalog::STATUS_EXPIRED]);
        } elseif ($persist === SubscriptionCatalog::STATUS_ACTIVE && $stored === SubscriptionCatalog::STATUS_EXPIRED) {
            $subscription->update(['status' => SubscriptionCatalog::STATUS_ACTIVE]);
        }

        return $subscription->fresh();
    }

    public function recordPayment(
        Subscription $subscription,
        float $amount,
        string $method,
        $paidAt,
        ?string $reference = null,
        ?string $notes = null,
        ?SubscriptionInvoice $invoice = null
    ): SubscriptionPayment {
        $payment = SubscriptionPayment::query()->create([
            'company_id' => $subscription->company_id,
            'subscription_id' => $subscription->id,
            'subscription_invoice_id' => $invoice ? $invoice->id : null,
            'recorded_by' => auth()->id(),
            'amount' => round($amount, 2),
            'method' => $method,
            'reference' => $reference,
            'paid_at' => $paidAt,
            'notes' => $notes,
        ]);

        if ($invoice && $invoice->company_id === $subscription->company_id) {
            $invoice->update([
                'status' => SubscriptionInvoice::STATUS_PAID,
                'paid_at' => Carbon::parse($paidAt)->toDateString(),
            ]);
        }

        $this->audit->record('payment', 'subscriptions', $subscription->company, null, [
            'amount' => $payment->amount,
            'method' => $method,
            'reference' => $reference,
            'invoice' => $invoice->invoice_number ?? null,
        ]);

        return $payment;
    }

    public function issueInvoice(
        Company $company,
        float $amount,
        Carbon $dueDate,
        ?string $email = null,
        ?string $notes = null,
        bool $sendEmail = true
    ): array {
        $subscription = $company->subscription()->with('plan')->first();
        if (! $subscription) {
            throw new InvalidArgumentException('This business has no subscription to invoice.');
        }

        $invoice = SubscriptionInvoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'issued_by' => auth()->id(),
            'invoice_number' => $this->nextInvoiceNumber(),
            'status' => SubscriptionInvoice::STATUS_SENT,
            'amount' => round($amount, 2),
            'currency' => $company->currency_code ?: config('sellix.currency_code', 'KES'),
            'due_date' => $dueDate->toDateString(),
            'sent_at' => now(),
            'sent_to_email' => $email ?: $company->email,
            'plan_name' => optional($subscription->plan)->name,
            'notes' => $notes,
        ]);

        $this->recordHistory($subscription, SubscriptionCatalog::ACTION_INVOICE_SENT, [
            'new_plan_id' => $subscription->subscription_plan_id,
            'notes' => 'Invoice '.$invoice->invoice_number.' for '.number_format($invoice->amount, 2),
        ]);

        $this->audit->record('invoice', 'subscriptions', $company, null, [
            'invoice_number' => $invoice->invoice_number,
            'amount' => $invoice->amount,
            'email' => $invoice->sent_to_email,
        ]);

        $mailed = false;
        $mailError = null;
        if ($sendEmail && $invoice->sent_to_email) {
            try {
                Mail::to($invoice->sent_to_email)->send(new SubscriptionInvoiceMail($invoice));
                $mailed = true;
            } catch (\Throwable $e) {
                $mailError = $e->getMessage();
            }
        }

        return [
            'invoice' => $invoice->fresh(['company', 'subscription.plan']),
            'mailed' => $mailed,
            'mail_error' => $mailError,
        ];
    }

    public function nextInvoiceNumber(): string
    {
        $next = ((int) SubscriptionInvoice::query()->max('id')) + 1;

        return 'SUB-INV-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function addPeriod(Carbon $from, SubscriptionPlan $plan): Carbon
    {
        $date = $from->copy()->startOfDay();

        switch ($plan->billing_period) {
            case SubscriptionCatalog::PERIOD_QUARTERLY:
                return $date->addMonthsNoOverflow(3);
            case SubscriptionCatalog::PERIOD_SEMI_ANNUALLY:
                return $date->addMonthsNoOverflow(6);
            case SubscriptionCatalog::PERIOD_ANNUALLY:
                return $date->addYearNoOverflow();
            case SubscriptionCatalog::PERIOD_CUSTOM:
                $days = max(1, (int) $plan->duration_days);
                return $date->addDays($days);
            case SubscriptionCatalog::PERIOD_MONTHLY:
            default:
                return $date->addMonthNoOverflow();
        }
    }

    protected function recordHistory(Subscription $subscription, string $action, array $payload): void
    {
        SubscriptionHistory::query()->create([
            'company_id' => $subscription->company_id,
            'subscription_id' => $subscription->id,
            'actor_id' => auth()->id(),
            'action' => $action,
            'previous_plan_id' => $payload['previous_plan_id'] ?? null,
            'new_plan_id' => $payload['new_plan_id'] ?? null,
            'previous_expires_at' => $payload['previous_expires_at'] ?? null,
            'new_expires_at' => $payload['new_expires_at'] ?? null,
            'previous_status' => $payload['previous_status'] ?? null,
            'new_status' => $payload['new_status'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'ip_address' => request() ? request()->ip() : null,
        ]);
    }
}
