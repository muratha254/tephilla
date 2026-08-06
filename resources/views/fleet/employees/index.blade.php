@extends('layouts.fleet')

@section('title', 'Employee Management')

@push('css')
<link rel="stylesheet" href="{{ asset('css/fleet-vehicles.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-vendors.css') }}?v=2">
<link rel="stylesheet" href="{{ asset('css/fleet-employees.css') }}?v=1">
@endpush

@section('content')
<div class="fleet-page-head">
    <h1 class="fleet-page-title">Employee Management</h1>
    <ul class="fleet-breadcrumb fleet-breadcrumb-right">
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Employee Management</li>
    </ul>
</div>

@if (session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="fleet-vehicle-toolbar">
    <div></div>
    <a href="{{ route('employees.create') }}" class="fleet-btn fleet-btn-primary"><i class="fa fa-plus"></i> Add Employee</a>
</div>

<div class="fleet-panel fleet-vendor-card">
    <form method="GET" action="{{ route('employees.index') }}" class="fleet-vendor-table-tools">
        <div></div>
        <div class="fleet-vendor-search">
            <label for="employee-search">Search:</label>
            <input type="text" id="employee-search" name="search" value="{{ $search }}" placeholder="Name, email, username...">
        </div>
    </form>

    <div class="fleet-table-wrap">
        <table class="fleet-vendor-table fleet-employee-table">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>Username</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $index => $employee)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><div class="fleet-vehicle-name">{{ $employee->fullName() }}</div></td>
                    <td>{{ $employee->mobile }}</td>
                    <td>{{ $employee->email }}</td>
                    <td>{{ $employee->username }}</td>
                    <td>
                        <span class="fleet-status-tag {{ $employee->is_active ? 'fleet-status-active' : 'fleet-status-inactive' }}">
                            {{ $employee->statusLabel() }}
                        </span>
                    </td>
                    <td>
                        <div class="fleet-action-btns">
                            <a href="{{ route('employees.edit', $employee) }}" class="fleet-action-btn edit-green" title="Edit"><i class="fa fa-pencil"></i></a>
                            <form action="{{ route('employees.destroy', $employee) }}" method="POST" class="fleet-delete-form" onsubmit="return confirm('Delete this employee?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="fleet-action-btn delete" title="Delete"><i class="fa fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="fleet-empty-row">No employees found. <a href="{{ route('employees.create') }}">Add your first employee</a>.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
