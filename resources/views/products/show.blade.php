@extends('layouts.fleet')

@section('title', 'Item profile')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Item profile',
    'backUrl' => route('products.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Item', 'url' => route('products.index')],
        ['label' => 'Profile'],
    ],
])

@php
    $money = function ($amount) use ($currencyCode) {
        return $currencyCode . ' ' . number_format((float) $amount, 2);
    };
    $currentBranch = $branch->name ?? 'MAIN';
@endphp

<div class="sx-profile-card">
    <div class="sx-profile-top">
        <div class="sx-profile-photo">
            @if($product->image_path)
                <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}">
            @else
                <div class="sx-no-photo" aria-hidden="true">
                    <i class="fa fa-camera"></i>
                </div>
            @endif
        </div>
        <div class="sx-profile-info">
            <h3 class="sx-basic-title">Basic Info</h3>
            <div class="sx-info-grid">
                <div><span>Name:</span> <strong>{{ $product->name }}</strong></div>
                <div><span>Code:</span> <strong>{{ $product->item_code }}</strong></div>
                <div><span>Unit:</span> <strong>{{ strtoupper(optional($product->unit)->name ?: '-') }}</strong></div>
                <div><span>Brand:</span> <strong>{{ optional($product->brand)->name ?: '-' }}</strong></div>
                <div><span>Active Price:</span> <strong>{{ $money($product->selling_price) }}</strong></div>
                <div><span>Category:</span> <strong>{{ optional($product->category)->name ?: '-' }}</strong></div>
                <div><span>Alert Qty:</span> <strong>{{ (float) $product->reorder_level == (int) $product->reorder_level ? (int) $product->reorder_level : $product->reorder_level }}</strong></div>
                <div><span>Tax Type:</span> <strong>{{ $product->tax_inclusive ? 'Inclusive' : 'Exclusive' }}</strong></div>
                <div><span>Expiry Date:</span> <strong>{{ optional($product->expiry_date)->format('d-m-Y') ?: '-' }}</strong></div>
                <div class="sx-info-wide"><span>Description:</span> <strong>{{ $product->description ?: '-' }}</strong></div>
            </div>
            <div class="sx-profile-actions">
                @if($canUpdate)
                    <form action="{{ route('products.image', $product) }}" method="post" enctype="multipart/form-data" class="sx-inline-upload">
                        @csrf
                        <input type="file" name="image" id="sx-change-image" accept="image/*" onchange="this.form.submit()">
                        <button type="button" class="btn btn-primary btn-xs" onclick="document.getElementById('sx-change-image').click()">Change image</button>
                    </form>
                    <a href="{{ route('products.edit', $product) }}" class="btn btn-success btn-xs">Edit Info</a>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="sx-profile-section">
    <div class="sx-ribbon">Batches</div>
    <div class="table-responsive">
        <table class="table table-bordered sx-cyan-table">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Branch</th>
                    <th>Date</th>
                    <th>Expiry</th>
                    <th>Cost P</th>
                    <th>Retail P</th>
                    <th>Wholesale P</th>
                    <th>Promotion P</th>
                    <th>Stocked Qty</th>
                    <th>Balance Qty</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($batches as $index => $batch)
                    <tr>
                        <td>{{ $index + 1 }}.</td>
                        <td>{{ optional($batch->branch)->name }}</td>
                        <td>{{ optional($batch->batch_date)->format('Y-m-d') }}</td>
                        <td>{{ optional($batch->expiry_date)->format('Y-m-d') }}</td>
                        <td>{{ number_format((float) $batch->cost_price, 2) }}</td>
                        <td>{{ number_format((float) $batch->retail_price, 2) }}</td>
                        <td>{{ number_format((float) $batch->wholesale_price, 2) }}</td>
                        <td>{{ number_format((float) $batch->promo_price, 2) }}</td>
                        <td>{{ (float) $batch->stocked_qty == (int) $batch->stocked_qty ? (int) $batch->stocked_qty : $batch->stocked_qty }}</td>
                        <td>{{ (float) $batch->balance_qty == (int) $batch->balance_qty ? (int) $batch->balance_qty : $batch->balance_qty }}</td>
                        <td>
                            @if($batch->is_active)
                                <span class="label label-success">Active</span>
                            @else
                                <span class="label label-default">Inactive</span>
                            @endif
                        </td>
                        <td>
                            @if($canCreate)
                                <button type="button" class="btn btn-success btn-xs sx-open-child-stock"
                                    data-batch="{{ $batch->id }}"
                                    data-name="{{ $product->name }}"
                                    data-rate="{{ $product->conversion_rate ?: 1 }}"
                                    data-url="{{ route('products.child-stock.store', $product) }}">
                                    Create Child <i class="fa fa-download"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="sx-none-found">No record found!!!</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="sx-profile-section">
    <div class="sx-orange-bar">Child Item</div>
    <div class="table-responsive">
        <table class="table table-bordered sx-cyan-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Branch</th>
                    <th>Purchase Price</th>
                    <th>Sale Price</th>
                    <th>Available Stock</th>
                    <th>Reorder Level</th>
                    <th>Control Qty</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($children as $child)
                    <tr>
                        <td>{{ $child->name }}</td>
                        <td>{{ $currentBranch }}</td>
                        <td>{{ number_format((float) $child->purchase_price, 2) }}</td>
                        <td>{{ number_format((float) $child->selling_price, 2) }}</td>
                        <td>{{ number_format((float) ($childStock[$child->id] ?? 0), 2) }}</td>
                        <td>{{ (float) $child->reorder_level == (int) $child->reorder_level ? (int) $child->reorder_level : $child->reorder_level }}</td>
                        <td>{{ (float) $child->conversion_rate == (int) $child->conversion_rate ? (int) $child->conversion_rate : $child->conversion_rate }}</td>
                        <td>
                            @if($child->is_active)
                                <span class="label label-success">Active</span>
                            @else
                                <span class="label label-default">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('products.show', $child) }}" class="btn btn-primary btn-xs">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="sx-none-found">No record found!!!</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('products.partials.child-stock-modal')
@endsection

@push('scripts')
@include('products.partials.child-stock-script')
@endpush
