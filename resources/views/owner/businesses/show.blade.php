@extends('layouts.fleet')

@section('title', $company->name)

@section('content')
@php
    $sub = $company->subscription;
    $st = $sub ? $sub->effectiveStatus() : 'expired';
    if (! $company->is_active) { $st = 'deactivated'; }
    $days = $sub ? $sub->daysRemaining() : 0;
    $progress = 0;
    if ($sub && $sub->starts_at && $sub->expires_at) {
        $total = max(1, $sub->starts_at->diffInDays($sub->expires_at));
        $used = $sub->starts_at->diffInDays(now());
        $progress = max(0, min(100, round(100 - (($days / $total) * 100))));
    }
@endphp
@include('layouts.partials.page-header', [
    'title' => $company->name,
    'subtitle' => 'Subscription, users, shops and history',
    'backUrl' => route('owner.businesses.index'),
    'breadcrumbs' => [
        ['label' => 'Owner', 'url' => route('owner.dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Businesses', 'url' => route('owner.businesses.index')],
        ['label' => $company->name],
    ],
])

<div class="sx-po-pay-meta">
    <div><span>Business ID</span> <strong>{{ $company->id }}</strong></div>
    <div><span>Owner</span> <strong>{{ $company->owner_name ?: '-' }}</strong></div>
    <div><span>Email</span> <strong>{{ $company->email ?: '-' }}</strong></div>
    <div><span>Phone</span> <strong>{{ $company->phone ?: '-' }}</strong></div>
    <div><span>Plan</span> <strong>{{ optional(optional($sub)->plan)->name ?: '-' }}</strong></div>
    <div><span>Status</span> <strong><span class="sx-sub-badge {{ subscription_status_class($st) }}">{{ $sub ? $sub->statusLabel() : 'None' }}</span></strong></div>
    <div><span>Start</span> <strong>{{ optional(optional($sub)->starts_at)->format('d M Y') ?: '-' }}</strong></div>
    <div><span>Expiry</span> <strong>{{ optional(optional($sub)->expires_at)->format('d M Y') ?: '-' }}</strong></div>
    <div><span>Days remaining</span> <strong>{{ $sub ? $days : '-' }}</strong></div>
    <div><span>Users</span> <strong>{{ $usage['users'] }} (unlimited)</strong></div>
    <div><span>Shops</span> <strong>{{ $usage['branches'] }} / {{ $usage['max_branches'] ?: 'Unlimited' }}</strong></div>
    <div><span>Created</span> <strong>{{ $company->created_at->format('d M Y') }}</strong></div>
</div>

<div class="progress" style="height:10px;margin:12px 0 20px;">
    <div class="progress-bar" style="width: {{ $progress }}%; background:#c9a027;"></div>
</div>

@if($sub && $st === 'pending_approval')
<div class="sx-box" style="border-left:4px solid #c9a027;">
    <div class="sx-box-body">
        <h4>Pending subscription request</h4>
        <p>This client selected <strong>{{ optional($sub->plan)->name }}</strong> and is waiting for approval. They cannot access the dashboard until you approve.</p>
        <form method="post" action="{{ route('owner.businesses.approve', $company) }}" class="sx-owner-confirm" data-title="Approve this request" style="display:inline-block;margin-right:8px;">
            @csrf
            <input type="text" name="notes" class="form-control" placeholder="Notes (optional)" style="margin-bottom:8px;min-width:260px;">
            <button type="submit" class="btn btn-success">Approve</button>
        </form>
        <form method="post" action="{{ route('owner.businesses.reject', $company) }}" class="sx-owner-confirm" data-title="Reject this request" data-danger="1" style="display:inline-block;">
            @csrf
            <input type="text" name="notes" class="form-control" placeholder="Reason (optional)" style="margin-bottom:8px;min-width:260px;">
            <button type="submit" class="btn btn-danger">Reject</button>
        </form>
    </div>
</div>
@endif

<p>
    <a href="{{ route('owner.businesses.edit', $company) }}" class="btn sx-btn-aqua"><i class="fa fa-pencil"></i> Edit</a>
</p>

@if($sub)
<div class="row">
    <div class="col-md-6">
        <div class="sx-box">
            <div class="sx-box-body">
                <h4>Renew</h4>
                <form method="post" action="{{ route('owner.businesses.renew', $company) }}" class="sx-owner-confirm" data-title="Renew subscription" data-text="Add one plan period to this expiry date.">
                    @csrf
                    <div class="form-group">
                        <label>Plan</label>
                        <select name="plan_id" class="form-control">
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" @if((int) $plan->id === (int) $sub->subscription_plan_id) selected @endif>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start from date (optional)</label>
                        <input type="date" name="from_date" class="form-control">
                        <p class="help-block">If the subscription is expired, renewal starts today unless you pick another date.</p>
                    </div>
                    <div class="form-group">
                        <label>Payment amount</label>
                        <input type="number" step="0.01" min="0" name="amount" class="form-control" value="{{ optional($sub->plan)->price }}">
                    </div>
                    <div class="form-group">
                        <label>Method</label>
                        <select name="method" class="form-control">
                            @foreach($paymentMethods as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reference</label>
                        <input type="text" name="reference" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Paid at</label>
                        <input type="date" name="paid_at" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Renew</button>
                </form>
            </div>
        </div>
        <div class="sx-box">
            <div class="sx-box-body">
                <h4>Extend / change plan</h4>
                <form method="post" action="{{ route('owner.businesses.extend', $company) }}" class="sx-owner-confirm" data-title="Extend subscription">
                    @csrf
                    <div class="form-group">
                        <label>Extra days</label>
                        <input type="number" min="1" name="days" class="form-control" value="30" required>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-success">Extend</button>
                </form>
                <hr>
                <form method="post" action="{{ route('owner.businesses.change-plan', $company) }}" class="sx-owner-confirm" data-title="Change plan">
                    @csrf
                    <div class="form-group">
                        <label>New plan</label>
                        <select name="plan_id" class="form-control" required>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary">Change plan</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="sx-box">
            <div class="sx-box-body">
                <h4>Status actions</h4>
                <form method="post" action="{{ route('owner.businesses.suspend', $company) }}" class="sx-owner-confirm" data-title="Suspend this business" data-danger="1" style="margin-bottom:12px;">
                    @csrf
                    <input type="text" name="notes" class="form-control" placeholder="Reason" style="margin-bottom:8px;">
                    <button type="submit" class="btn btn-warning">Suspend</button>
                </form>
                <form method="post" action="{{ route('owner.businesses.activate', $company) }}" class="sx-owner-confirm" data-title="Activate subscription" style="margin-bottom:12px;">
                    @csrf
                    <input type="text" name="notes" class="form-control" placeholder="Notes" style="margin-bottom:8px;">
                    <button type="submit" class="btn btn-success">Activate subscription</button>
                </form>
                <form method="post" action="{{ route('owner.businesses.reset', $company) }}" class="sx-owner-confirm" data-title="Reset status from dates" style="margin-bottom:12px;">
                    @csrf
                    <button type="submit" class="btn btn-default">Reset status</button>
                </form>
                <form method="post" action="{{ route('owner.businesses.cancel', $company) }}" class="sx-owner-confirm" data-title="Cancel subscription" data-danger="1" style="margin-bottom:12px;">
                    @csrf
                    <input type="text" name="notes" class="form-control" placeholder="Reason" style="margin-bottom:8px;">
                    <button type="submit" class="btn btn-danger">Cancel</button>
                </form>
                @if($company->is_active)
                    <form method="post" action="{{ route('owner.businesses.deactivate', $company) }}" class="sx-owner-confirm" data-title="Deactivate the whole business" data-danger="1">
                        @csrf
                        <button type="submit" class="btn btn-danger">Deactivate business</button>
                    </form>
                @else
                    <form method="post" action="{{ route('owner.businesses.activate-business', $company) }}" class="sx-owner-confirm" data-title="Reactivate this business">
                        @csrf
                        <button type="submit" class="btn btn-success">Reactivate business</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="sx-box">
            <div class="sx-box-body">
                <h4>Send invoice</h4>
                <form method="post" action="{{ route('owner.businesses.invoice', $company) }}">
                    @csrf
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required value="{{ old('amount', optional(optional($sub)->plan)->price) }}">
                    </div>
                    <div class="form-group">
                        <label>Due date</label>
                        <input type="date" name="due_date" class="form-control" required value="{{ old('due_date', now()->addDays(7)->toDateString()) }}">
                    </div>
                    <div class="form-group">
                        <label>Send to email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $company->email) }}" placeholder="Business owner email">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Shown on the invoice">{{ old('notes') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send invoice</button>
                </form>
            </div>
        </div>
        <div class="sx-box">
            <div class="sx-box-body">
                <h4>Record payment</h4>
                <form method="post" action="{{ route('owner.businesses.payment', $company) }}">
                    @csrf
                    <div class="form-group">
                        <label>Invoice (optional)</label>
                        <select name="invoice_id" class="form-control">
                            <option value="">No invoice</option>
                            @foreach($unpaidInvoices ?? [] as $inv)
                                <option value="{{ $inv->id }}">{{ $inv->invoice_number }} — {{ number_format((float) $inv->amount, 2) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Method</label>
                        <select name="method" class="form-control" required>
                            @foreach($paymentMethods as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reference</label>
                        <input type="text" name="reference" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Paid at</label>
                        <input type="date" name="paid_at" class="form-control" required value="{{ now()->toDateString() }}">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Save payment</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<div class="sx-box">
    <div class="sx-box-body">
        <h4>Users</h4>
        <ul>
            @foreach($company->users as $user)
                <li>{{ $user->name }} ({{ $user->email }}) — {{ optional($user->role)->display_name }} {{ $user->is_active ? '' : '[inactive]' }}</li>
            @endforeach
        </ul>
        <h4>Shops / branches</h4>
        <ul>
            @foreach($company->branches as $branch)
                <li>{{ $branch->name }} ({{ $branch->code }})</li>
            @endforeach
        </ul>
    </div>
</div>

<div class="sx-box">
    <div class="sx-box-body">
        <h4>Subscription history</h4>
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Action</th>
                        <th>Previous plan</th>
                        <th>New plan</th>
                        <th>Previous expiry</th>
                        <th>New expiry</th>
                        <th>By</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $row)
                        <tr>
                            <td>{{ $row->created_at->format('d M Y H:i') }}</td>
                            <td>{{ str_replace('_', ' ', $row->action) }}</td>
                            <td>{{ optional($row->previousPlan)->name ?: '-' }}</td>
                            <td>{{ optional($row->newPlan)->name ?: '-' }}</td>
                            <td>{{ optional($row->previous_expires_at)->format('d M Y') ?: '-' }}</td>
                            <td>{{ optional($row->new_expires_at)->format('d M Y') ?: '-' }}</td>
                            <td>{{ optional($row->actor)->name ?: '-' }}</td>
                            <td>{{ $row->notes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <h4>Invoices</h4>
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Amount</th>
                        <th>Due</th>
                        <th>Email</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices ?? [] as $invoice)
                        <tr>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</td>
                            <td>{{ optional($invoice->due_date)->format('d M Y') }}</td>
                            <td>{{ $invoice->sent_to_email ?: '-' }}</td>
                            <td>{{ $invoice->displayStatus() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No invoices sent yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <h4>Payments</h4>
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Invoice</th>
                        <th>Recorded by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $pay)
                        <tr>
                            <td>{{ optional($pay->paid_at)->format('d M Y') }}</td>
                            <td>{{ number_format((float) $pay->amount, 2) }}</td>
                            <td>{{ $pay->method }}</td>
                            <td>{{ $pay->reference ?: '-' }}</td>
                            <td>{{ optional($pay->invoice)->invoice_number ?: '-' }}</td>
                            <td>{{ optional($pay->recorder)->name ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No payments recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).on('submit', 'form.sx-owner-confirm', function (e) {
    var form = this;
    if (form.getAttribute('data-ok') === '1') return true;
    e.preventDefault();
    var danger = form.getAttribute('data-danger') === '1';
    Swal.fire({
        icon: danger ? 'warning' : 'question',
        title: form.getAttribute('data-title') || 'Confirm',
        text: form.getAttribute('data-text') || 'Continue with this action?',
        showCancelButton: true,
        confirmButtonColor: danger ? '#dd4b39' : '#00a65a',
        confirmButtonText: 'Yes, continue'
    }).then(function (result) {
        if (!result.isConfirmed) return;
        form.setAttribute('data-ok', '1');
        HTMLFormElement.prototype.submit.call(form);
    });
});
</script>
@endpush
