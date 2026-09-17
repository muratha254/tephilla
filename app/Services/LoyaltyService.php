<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function __construct(private SettingsService $settings)
    {
    }

    public function earnForSale(Sale $sale): ?LoyaltyTransaction
    {
        if ($sale->status !== Sale::STATUS_COMPLETED || ! $sale->customer_id) {
            return null;
        }

        $existing = LoyaltyTransaction::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', LoyaltyTransaction::TYPE_EARN)
            ->first();
        if ($existing) {
            return $existing;
        }

        $rate = (float) $this->settings->get((int) $sale->company_id, 'loyalty_points_rate', 0);
        if ($rate <= 0) {
            return null;
        }

        $points = round(((float) $sale->total) * $rate, 2);
        if ($points <= 0) {
            return null;
        }

        return DB::transaction(function () use ($sale, $points) {
            $customer = Customer::query()->lockForUpdate()->find($sale->customer_id);
            if (! $customer) {
                return null;
            }

            $balance = round((float) $customer->loyalty_points + $points, 2);
            $customer->update(['loyalty_points' => $balance]);

            return LoyaltyTransaction::query()->create([
                'company_id' => $sale->company_id,
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'type' => LoyaltyTransaction::TYPE_EARN,
                'points' => $points,
                'balance_after' => $balance,
                'source_type' => Sale::class,
                'source_id' => $sale->id,
                'reference' => $sale->number,
                'notes' => 'Points earned on sale',
            ]);
        });
    }

    public function reverseForSale(Sale $sale): ?LoyaltyTransaction
    {
        $earn = LoyaltyTransaction::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', LoyaltyTransaction::TYPE_EARN)
            ->first();
        if (! $earn) {
            return null;
        }

        $existing = LoyaltyTransaction::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', LoyaltyTransaction::TYPE_REVERSE)
            ->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($sale, $earn) {
            $customer = Customer::query()->lockForUpdate()->find($sale->customer_id);
            if (! $customer) {
                return null;
            }

            $points = (float) $earn->points;
            $balance = round(max(0, (float) $customer->loyalty_points - $points), 2);
            $customer->update(['loyalty_points' => $balance]);

            return LoyaltyTransaction::query()->create([
                'company_id' => $sale->company_id,
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'type' => LoyaltyTransaction::TYPE_REVERSE,
                'points' => -$points,
                'balance_after' => $balance,
                'source_type' => Sale::class,
                'source_id' => $sale->id,
                'reference' => $sale->number,
                'notes' => 'Points reversed for voided/cancelled sale',
            ]);
        });
    }

    public function adjustForCreditNote(CreditNote $note): ?LoyaltyTransaction
    {
        if (! $note->customer_id || $note->loyalty_adjusted) {
            return null;
        }

        $existing = LoyaltyTransaction::query()
            ->where('source_type', CreditNote::class)
            ->where('source_id', $note->id)
            ->where('type', LoyaltyTransaction::TYPE_REVERSE)
            ->first();
        if ($existing) {
            return $existing;
        }

        $rate = (float) $this->settings->get((int) $note->company_id, 'loyalty_points_rate', 0);
        if ($rate <= 0) {
            return null;
        }

        $points = round(((float) $note->total) * $rate, 2);
        if ($points <= 0) {
            return null;
        }

        return DB::transaction(function () use ($note, $points) {
            $customer = Customer::query()->lockForUpdate()->find($note->customer_id);
            if (! $customer) {
                return null;
            }

            $balance = round(max(0, (float) $customer->loyalty_points - $points), 2);
            $customer->update(['loyalty_points' => $balance]);

            $tx = LoyaltyTransaction::query()->create([
                'company_id' => $note->company_id,
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'type' => LoyaltyTransaction::TYPE_REVERSE,
                'points' => -$points,
                'balance_after' => $balance,
                'source_type' => CreditNote::class,
                'source_id' => $note->id,
                'reference' => $note->number,
                'notes' => 'Points adjusted for credit note',
            ]);

            $note->update(['loyalty_adjusted' => true]);

            return $tx;
        });
    }
}
