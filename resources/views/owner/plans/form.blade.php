@extends('layouts.fleet')

@section('title', $plan->exists ? 'Edit plan' : 'New plan')

@section('content')
@include('layouts.partials.page-header', [
    'title' => $plan->exists ? 'Edit plan' : 'New plan',
    'subtitle' => '',
    'backUrl' => route('owner.plans.index'),
    'breadcrumbs' => [
        ['label' => 'Owner', 'url' => route('owner.dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Plans', 'url' => route('owner.plans.index')],
        ['label' => $plan->exists ? 'Edit' : 'New'],
    ],
])

<div class="sx-box">
    <div class="sx-box-body">
        <form method="post" action="{{ $plan->exists ? route('owner.plans.update', $plan) : route('owner.plans.store') }}">
            @csrf
            @if($plan->exists)
                @method('PUT')
            @endif
            <div class="form-group">
                <label class="sx-req">Name*</label>
                <input type="text" name="name" class="form-control" required value="{{ old('name', $plan->name) }}">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description', $plan->description) }}</textarea>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="sx-req">Price*</label>
                        <input type="number" step="0.01" min="0" name="price" class="form-control" required value="{{ old('price', $plan->price) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="sx-req">Billing period*</label>
                        <select name="billing_period" class="form-control" required>
                            @foreach($periods as $value => $label)
                                <option value="{{ $value }}" @if(old('billing_period', $plan->billing_period) === $value) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Custom days</label>
                        <input type="number" min="1" name="duration_days" class="form-control" value="{{ old('duration_days', $plan->duration_days) }}">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Max shops / branches</label>
                        <input type="number" min="1" name="max_branches" class="form-control" value="{{ old('max_branches', $plan->max_branches) }}" placeholder="Blank = unlimited">
                        <p class="help-block">This is the only plan limit. Users and roles are not restricted.</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Sort</label>
                        <input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', $plan->sort_order) }}">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <p class="help-block" style="margin-top:0;">Every plan includes all modules and all user roles (Super Admin, Company Admin, Cashier, HR Officer, and the rest). Only the number of shops/branches changes.</p>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" @if(old('is_active', $plan->is_active)) checked @endif>
                    Active
                </label>
            </div>
            <div class="sx-form-actions">
                <button type="submit" class="btn btn-success">Save</button>
                <a href="{{ route('owner.plans.index') }}" class="btn btn-danger">Close</a>
            </div>
        </form>
    </div>
</div>
@endsection
