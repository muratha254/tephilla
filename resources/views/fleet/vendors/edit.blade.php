@extends('layouts.fleet')

@section('title', 'Edit Vehicle Vendor')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Edit Vehicle Vendor</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Home</a></li>
        <li><a href="{{ route('vehicle-vendors.index') }}">Vehicle Vendors</a></li>
        <li>Edit Vehicle Vendor</li>
    </ul>
</div>

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="fleet-form-errors">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="fleet-panel fleet-vendor-add-card">
    <div class="fleet-vendor-add-card-head">
        <h2>Edit Vendor Info</h2>
        <a href="{{ route('vehicle-vendors.index') }}" class="fleet-btn-back-list"><i class="fa fa-arrow-left"></i> Back to List</a>
    </div>
    <div class="fleet-panel-body fleet-vendor-add-body">
        <form action="{{ route('vehicle-vendors.update', $vendor) }}" method="POST" enctype="multipart/form-data" class="fleet-vendor-form">
            @csrf
            @method('PUT')
            @include('fleet.vendors.partials.form', ['vendor' => $vendor])
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var fileInput = document.getElementById('vendor-contract-file');
    var fileName = document.getElementById('vendor-contract-name');
    if (!fileInput || !fileName) return;

    fileInput.addEventListener('change', function () {
        fileName.value = this.files[0] ? this.files[0].name : 'Choose file';
    });
})();
</script>
@endpush
