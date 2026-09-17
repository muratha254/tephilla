@extends('layouts.fleet')
@section('title', 'HR Dashboard')

@section('content')
@php
    $money = function ($amount) use ($currencySymbol) {
        return $currencySymbol . ' ' . number_format((float) $amount, 2);
    };
@endphp

@include('layouts.partials.page-header', [
    'title' => 'HR Dashboard',
    'backUrl' => route('dashboard'),
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => route('dashboard'), 'icon' => 'fa-home'],
    ],
])

<div class="row sx-hr-stats">
    <div class="col-md-3 col-sm-6">
        <div class="sx-info-box sx-aqua">
            <div class="sx-info-icon"><i class="fa fa-users"></i></div>
            <div class="sx-info-body">
                <span class="sx-info-label">Active Employees</span>
                <span class="sx-info-value">{{ $stats['activeEmployees'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="sx-info-box sx-red">
            <div class="sx-info-icon"><i class="fa fa-users"></i></div>
            <div class="sx-info-body">
                <span class="sx-info-label">Inactive Employees</span>
                <span class="sx-info-value">{{ $stats['inactiveEmployees'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="sx-info-box sx-green">
            <div class="sx-info-icon"><i class="fa fa-flag"></i></div>
            <div class="sx-info-body">
                <span class="sx-info-label">Departments</span>
                <span class="sx-info-value">{{ $stats['departments'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="sx-info-box sx-hr-blue">
            <div class="sx-info-icon"><i class="fa fa-flag"></i></div>
            <div class="sx-info-body">
                <span class="sx-info-label">Designations</span>
                <span class="sx-info-value">{{ $stats['designations'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="sx-info-box sx-green">
            <div class="sx-info-icon"><i class="fa fa-flag"></i></div>
            <div class="sx-info-body">
                <span class="sx-info-label">Employee Category</span>
                <span class="sx-info-value">{{ $stats['categories'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="sx-info-box sx-hr-navy">
            <div class="sx-info-icon"><i class="fa fa-briefcase"></i></div>
            <div class="sx-info-body">
                <span class="sx-info-label">Paid Salaries</span>
                <span class="sx-info-value">{{ $money($stats['paidSalaries']) }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="sx-info-box sx-green">
            <div class="sx-info-icon"><i class="fa fa-briefcase"></i></div>
            <div class="sx-info-body">
                <span class="sx-info-label">Pending Salaries</span>
                <span class="sx-info-value">{{ $money($stats['pendingSalaries']) }}</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-7">
        <div class="sx-box sx-hr-panel">
            <div class="sx-box-header">
                <h3 class="sx-box-title"><i class="fa fa-folder-open"></i> Payroll Reports</h3>
            </div>
            <div class="sx-box-body">
                <div class="row sx-hr-report-grid">
                    <div class="col-sm-4">
                        <a href="#" class="sx-hr-report-card">
                            <div class="sx-hr-report-logo sx-hr-logo-kra">KRA</div>
                            <div class="sx-hr-report-name">KRA Reports</div>
                        </a>
                    </div>
                    <div class="col-sm-4">
                        <a href="#" class="sx-hr-report-card">
                            <div class="sx-hr-report-logo sx-hr-logo-shif">SHIF</div>
                            <div class="sx-hr-report-name">SHIF Reports</div>
                        </a>
                    </div>
                    <div class="col-sm-4">
                        <a href="#" class="sx-hr-report-card">
                            <div class="sx-hr-report-logo sx-hr-logo-nssf">NSSF</div>
                            <div class="sx-hr-report-name">NSSF Reports</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="sx-box sx-hr-panel">
            <div class="sx-box-header">
                <h3 class="sx-box-title"><i class="fa fa-list"></i> Departments</h3>
            </div>
            <div class="sx-box-body" style="min-height:auto;">
                @forelse($departmentTree as $i => $department)
                    <div class="sx-hr-dept-block">
                        <div class="sx-hr-dept-name">{{ $i + 1 }}. {{ $department['name'] }}</div>
                        @forelse($department['designations'] as $designation)
                            <div class="sx-hr-desig-row">
                                <span># {{ $designation['name'] }}</span>
                                <span class="badge sx-hr-count">{{ $designation['employees'] }}</span>
                            </div>
                        @empty
                            <div class="sx-hr-desig-row text-muted"><em>No designations</em></div>
                        @endforelse
                    </div>
                @empty
                    <p class="text-muted" style="margin:0;">No departments yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
