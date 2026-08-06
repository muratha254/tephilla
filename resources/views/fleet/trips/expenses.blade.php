@extends('layouts.fleet')

@section('title', 'Trip Expenses')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vehicle-form.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-trips.css') }}?v=5">
@endpush

@section('content')
<div class="fleet-page-head">
    <div class="fleet-page-title-row">
        <h1 class="fleet-page-title">Trip Expenses - {{ $trip->displayTripCode() }}</h1>
        <a href="{{ route('trips.show', $trip) }}" class="fleet-btn fleet-btn-outline"><i class="fa fa-arrow-left"></i> Back to Trip</a>
    </div>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('trips.index') }}">Trips</a></li>
        <li><a href="{{ route('trips.show', $trip) }}">Details</a></li>
        <li>Expenses</li>
    </ul>
</div>

@if (session('success'))
    <div class="fleet-alert fleet-alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="fleet-alert fleet-alert-error">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="fleet-trip-expense-layout">
    <div class="fleet-panel fleet-vehicle-card fleet-trip-expense-form-panel">
        <div class="fleet-panel-header">Add Expense</div>
        <div class="fleet-panel-body">
            <form method="POST" action="{{ route('trips.expenses.store', $trip) }}" class="fleet-trip-expense-form">
                @csrf
                <div class="fleet-trip-expense-form-grid">
                    <div class="fleet-field">
                        <label>Date</label>
                        <input type="date" name="expense_date" class="fleet-input" value="{{ old('expense_date', now()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="fleet-field">
                        <label>Category</label>
                        <select name="category" class="fleet-select fleet-input" required>
                            @foreach ($expenseCategories as $category)
                                <option value="{{ $category }}" {{ old('category') === $category ? 'selected' : '' }}>{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fleet-field">
                        <label>Amount (KSh)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="fleet-input" value="{{ old('amount') }}" placeholder="0.00" required>
                    </div>
                    <div class="fleet-field">
                        <label>Payment Method</label>
                        <select name="payment_method" class="fleet-select fleet-input" required>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method }}" {{ old('payment_method', 'Cash') === $method ? 'selected' : '' }}>{{ $method }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fleet-field fleet-field-wide">
                        <label>Description</label>
                        <input type="text" name="description" class="fleet-input" value="{{ old('description') }}" placeholder="Expense details">
                    </div>
                    <div class="fleet-field">
                        <label>Reference No.</label>
                        <input type="text" name="reference_no" class="fleet-input" value="{{ old('reference_no') }}" placeholder="Receipt / M-Pesa code">
                    </div>
                </div>
                <div class="fleet-trip-expense-form-actions">
                    <button type="submit" class="fleet-btn fleet-btn-primary"><i class="fa fa-plus"></i> Add Expense</button>
                </div>
            </form>
        </div>
    </div>

    <div class="fleet-panel fleet-vehicle-card">
        <div class="fleet-trip-expense-list-head">
            <h3>Expense List</h3>
            <div class="fleet-trip-expense-list-actions">
                <span class="fleet-trip-expense-total">Total: <strong>{{ format_kes($trip->totalExpensesAmount()) }}</strong></span>
                @if ($trip->expenses->count() > 0)
                    <a href="{{ route('trips.expense-pdf', $trip) }}" class="fleet-btn fleet-btn-primary" target="_blank" rel="noopener">
                        <i class="fa fa-file-pdf-o"></i> Export PDF
                    </a>
                @endif
            </div>
        </div>
        <div class="fleet-table-wrap">
            <table class="fleet-trip-list-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Payment</th>
                        <th>Reference</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trip->expenses as $index => $expense)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $expense->formattedDate() }}</td>
                        <td>{{ $expense->category }}</td>
                        <td>{{ $expense->description ?: '-' }}</td>
                        <td>{{ $expense->payment_method }}</td>
                        <td>{{ $expense->reference_no ?: '-' }}</td>
                        <td>{{ format_kes($expense->amount) }}</td>
                        <td>
                            <form action="{{ route('trips.expenses.destroy', [$trip, $expense]) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Remove this expense?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="fleet-empty-row">No expenses recorded yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
