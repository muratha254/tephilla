@extends('layouts.fleet')

@section('title', 'Edit Quotation')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Quotation',
    'subtitle' => 'Edit Quotation',
    'backUrl' => route('quotations.show', $quotation),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Quotations', 'url' => route('quotations.index')],
        ['label' => $quotation->number, 'url' => route('quotations.show', $quotation)],
        ['label' => 'Edit'],
    ],
])

<form method="post" action="{{ route('quotations.update', $quotation) }}" id="sx-quotation-form" class="sx-purchase-form">
    @csrf
    @method('PUT')
    @include('quotations._form')
</form>
@endsection

@push('scripts')
@include('quotations._form-scripts')
@endpush
