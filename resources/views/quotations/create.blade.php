@extends('layouts.fleet')

@section('title', 'New Quotation')

@section('content')
@include('layouts.partials.page-header', [
    'title' => 'Quotation',
    'subtitle' => 'Create Quotation',
    'backUrl' => route('quotations.index'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
        ['label' => 'Quotations', 'url' => route('quotations.index')],
        ['label' => 'New Quotation'],
    ],
])

<form method="post" action="{{ route('quotations.store') }}" id="sx-quotation-form" class="sx-purchase-form">
    @csrf
    @include('quotations._form')
</form>
@endsection

@push('scripts')
@include('quotations._form-scripts')
@endpush
