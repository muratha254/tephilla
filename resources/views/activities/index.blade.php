@extends('layouts.master')

@section('title')
    Activities
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Activities</li>
@endsection

@section('content')
@php($u = auth()->user())
<style>
.activities-section { margin-bottom: 24px; }
.activities-section .box-header { font-size: 15px; }
.activity-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.activity-link {
    display: flex; align-items: center; padding: 12px 14px;
    background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 4px;
    color: #333; text-decoration: none; transition: background .15s, border-color .15s;
}
.activity-link:hover { background: #f0f7ff; border-color: #3c8dbc; color: #222; }
.activity-link i { margin-right: 10px; font-size: 18px; width: 24px; text-align: center; }
.activity-link .badge-wrap { margin-left: auto; }
</style>

<div class="row">
    <div class="col-lg-12">
        <div class="box box-solid">
            <div class="box-body">
                <p class="text-muted" style="margin-bottom: 20px;">
                    <i class="fa fa-info-circle"></i> Use this page to access all activities in one place. Only items you have permission to use are shown.
                </p>

                <div class="activities-section">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-comments"></i> Chat</h3>
                        </div>
                        <div class="box-body">
                            <div class="activity-grid">
                                <a href="{{ route('chat.index') }}" class="activity-link"><i class="fa fa-comments"></i> Messages</a>
                            </div>
                        </div>
                    </div>
                </div>

                @if($u && ($u->hasModulePermission('inventory','read') || $u->hasRole('admin')))
                <div class="activities-section">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-archive"></i> Stock</h3>
                        </div>
                        <div class="box-body">
                            <div class="activity-grid">
                                <a href="{{ route('produk.index') }}" class="activity-link"><i class="fa fa-cubes"></i> Enter Stock</a>
                                <a href="{{ route('produk.incomplete') }}" class="activity-link">
                                    <i class="fa fa-plus-circle text-warning"></i> Quick Add
                                    @if($incompleteCount > 0)<span class="badge-wrap"><span class="label label-warning">{{ $incompleteCount }}</span></span>@endif
                                </a>
                                <a href="{{ route('produk.updated') }}" class="activity-link"><i class="fa fa-edit text-info"></i> Update Stock</a>
                                <a href="{{ route('produk.out_of_stock') }}" class="activity-link"><i class="fa fa-exclamation-triangle text-danger"></i> Out of Stock</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($u && ($u->hasModulePermission('inventory','read') || $u->hasRole('admin')))
                <div class="activities-section">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-building"></i> Shops</h3>
                        </div>
                        <div class="box-body">
                            <div class="activity-grid">
                                <a href="{{ route('shop.index') }}" class="activity-link"><i class="fa fa-list"></i> Shop List</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($u && ($u->hasModulePermission('inventory','read') || $u->hasRole('admin')))
                <div class="activities-section">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-truck"></i> Suppliers</h3>
                        </div>
                        <div class="box-body">
                            <div class="activity-grid">
                                <a href="{{ route('supplier.index') }}" class="activity-link"><i class="fa fa-list"></i> Supplier List</a>
                                <a href="{{ route('supplier.withdrawal.index') }}" class="activity-link"><i class="fa fa-hand-o-right"></i> Supplier Withdrawal</a>
                                <a href="{{ route('supplier.withdrawals') }}" class="activity-link"><i class="fa fa-history"></i> Withdrawals List</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($u && ($u->hasModulePermission('consignment','read') || $u->hasRole('admin')))
                <div class="activities-section">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-handshake-o"></i> Consignment</h3>
                        </div>
                        <div class="box-body">
                            <div class="activity-grid">
                                <a href="{{ route('payment.consignments') }}" class="activity-link"><i class="fa fa-th-list"></i> All Consignments</a>
                                <a href="{{ route('payment.pending') }}" class="activity-link"><i class="fa fa-clock-o"></i> Consignment List</a>
                                <a href="{{ route('payment.index') }}" class="activity-link"><i class="fa fa-check"></i> Paid Consignments</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($u && ($u->hasModulePermission('consignment','read') || $u->hasRole('admin')))
                <div class="activities-section">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-money"></i> Cash Sale</h3>
                        </div>
                        <div class="box-body">
                            <div class="activity-grid">
                                <a href="{{ route('payment.cash.sales-generated') }}" class="activity-link"><i class="fa fa-shopping-cart"></i> Cash Generated Sale</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($u && ($u->hasModulePermission('sales','read') || $u->hasRole('admin')))
                <div class="activities-section">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-shopping-cart"></i> Sales</h3>
                        </div>
                        <div class="box-body">
                            <div class="activity-grid">
                                <a href="{{ route('transaksi.baru') }}" class="activity-link"><i class="fa fa-shopping-bag"></i> POS</a>
                                <a href="{{ route('daily-cash.open-form') }}" class="activity-link"><i class="fa fa-sun-o"></i> Open Day</a>
                                <a href="{{ route('daily-cash.close-form') }}" class="activity-link"><i class="fa fa-moon-o"></i> Close Day</a>
                                @if($u && ($u->hasRole('admin') || $u->hasRole('manager')))
                                <a href="{{ route('transaksi.baru.management') }}" class="activity-link"><i class="fa fa-user-secret"></i> Taken by Management</a>
                                <a href="{{ route('penjualan.management.index') }}" class="activity-link"><i class="fa fa-list"></i> Management Sales List</a>
                                @endif
                                <a href="{{ route('transaksi.index') }}" class="activity-link"><i class="fa fa-bolt"></i> Active Daily Sale</a>
                                <a href="{{ route('transaksi.suspended') }}" class="activity-link"><i class="fa fa-pause"></i> Suspended Sales</a>
                                <a href="{{ route('penjualan.initiated_edits') }}" class="activity-link"><i class="fa fa-pencil"></i> Initiated Sale Edits</a>
                                @if($u && $u->hasRole('admin'))
                                <a href="{{ route('penjualan.index') }}" class="activity-link"><i class="fa fa-list-alt"></i> Sales List</a>
                                <a href="{{ route('penjualan.detailed-report') }}" class="activity-link"><i class="fa fa-file-text-o"></i> Today Sales Report</a>
                                <a href="{{ route('receipt-confirmation.index') }}" class="activity-link">
                                    <i class="fa fa-check-square-o"></i> Receipt Confirmation
                                    <span class="badge-wrap receipt-confirmation-badge-wrap" style="{{ $pendingReceiptCount > 0 ? '' : 'display:none;' }}"><span class="label label-warning receipt-confirmation-badge-count">{{ $pendingReceiptCount }}</span></span>
                                </a>
                                @endif
                                <a href="{{ route('driver-commission.index') }}" class="activity-link"><i class="fa fa-money"></i> Service Fee</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($u && ($u->hasModulePermission('expense','read') || $u->hasRole('admin')))
                <div class="activities-section">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-money"></i> Expenses</h3>
                        </div>
                        <div class="box-body">
                            <div class="activity-grid">
                                <a href="{{ route('pengeluaran.index') }}" class="activity-link"><i class="fa fa-list"></i> Expense List</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($u && ($u->hasModulePermission('payroll','read') || $u->hasRole('admin')))
                <div class="activities-section">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-briefcase"></i> Payroll & HR</h3>
                        </div>
                        <div class="box-body">
                            <div class="activity-grid">
                                <a href="{{ route('payroll.dashboard') }}" class="activity-link"><i class="fa fa-dashboard"></i> Payroll Dashboard</a>
                                <a href="{{ route('employee.index') }}" class="activity-link"><i class="fa fa-users"></i> Employees</a>
                                <a href="{{ route('payroll.index') }}" class="activity-link"><i class="fa fa-calendar"></i> Payroll</a>
                                <a href="{{ route('payroll.settings') }}" class="activity-link"><i class="fa fa-cog"></i> Tax Settings</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($u && ($u->hasModulePermission('payroll','read') || $u->hasRole('admin')))
                <div class="activities-section">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-bar-chart"></i> Reports</h3>
                        </div>
                        <div class="box-body">
                            <div class="activity-grid">
                                <a href="{{ route('sales-report.summary') }}" class="activity-link"><i class="fa fa-pie-chart"></i> Sales Summary</a>
                                <a href="{{ route('sales-report.detailed') }}" class="activity-link"><i class="fa fa-list"></i> Sales Detailed</a>
                                <a href="{{ route('laporan.index') }}" class="activity-link"><i class="fa fa-line-chart"></i> Income Report</a>
                                <a href="{{ route('report.product') }}" class="activity-link"><i class="fa fa-cubes"></i> Product Report</a>
                                <a href="{{ route('reports.supplier-payments') }}" class="activity-link"><i class="fa fa-truck"></i> Supplier Payments</a>
                                <a href="{{ route('report.index') }}" class="activity-link"><i class="fa fa-file-text"></i> Sales Reports</a>
                                <a href="{{ route('reports.kra-tax') }}" class="activity-link"><i class="fa fa-university"></i> KRA Tax Report</a>
                                <a href="{{ route('reports.daily-closing') }}" class="activity-link"><i class="fa fa-calendar-check-o"></i> Daily Closing</a>
                                <a href="{{ route('reports.salary-payments') }}" class="activity-link"><i class="fa fa-credit-card"></i> Salary Payment Report</a>
                                <a href="{{ route('account.index') }}" class="activity-link"><i class="fa fa-book"></i> Accounts</a>
                                <a href="{{ route('reports.sales-by-shop') }}" class="activity-link"><i class="fa fa-building"></i> Sales by Shop</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if($u && ($u->hasRole('admin') || $u->can_read))
                <div class="activities-section">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-cogs"></i> System</h3>
                        </div>
                        <div class="box-body">
                            <div class="activity-grid">
                                <a href="{{ route('user.index') }}" class="activity-link"><i class="fa fa-users"></i> Users</a>
                                @if($u && $u->hasRole('admin'))
                                <a href="{{ route('setting.index') }}" class="activity-link"><i class="fa fa-cog"></i> Settings</a>
                                <a href="{{ route('backup.index') }}" class="activity-link"><i class="fa fa-database"></i> Backup</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
