@extends('layouts.fleet')

@section('title', 'Plans')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Subscription plans',
    'subtitle' => 'Plans share all roles and modules. Only shop/branch limits differ.',
    'backUrl' => route('owner.dashboard'),
    'breadcrumbs' => [
        ['label' => 'Owner', 'url' => route('owner.dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Plans'],
    ],
])

<div class="sx-box">
    <div class="sx-items-toolbar">
        <div></div>
        <a href="{{ route('owner.plans.create') }}" class="btn sx-btn-aqua"><i class="fa fa-plus"></i> New plan</a>
    </div>
    <div class="sx-box-body">
        <div class="table-responsive">
            <table class="table table-bordered sx-gold-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Period</th>
                        <th>Shops</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                        <tr>
                            <td>{{ $plan->name }}</td>
                            <td>{{ number_format((float) $plan->price, 2) }}</td>
                            <td>{{ $plan->periodLabel() }}{{ $plan->duration_days ? ' ('.$plan->duration_days.' days)' : '' }}</td>
                            <td>{{ $plan->maxBranchesLabel() }}</td>
                            <td>{{ $plan->is_active ? 'Active' : 'Inactive' }}</td>
                            <td>
                                <a href="{{ route('owner.plans.edit', $plan) }}" class="btn btn-xs btn-primary">Edit</a>
                                <form action="{{ route('owner.plans.destroy', $plan) }}" method="post" class="sx-swal-delete" style="display:inline;" data-swal-text="Archive this plan?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No plans yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
