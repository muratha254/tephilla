<!-- Sidebar category styling: public/css/sidebar-treeview-highlight.css (linked in master after skins) -->
<!-- Left side column. contains the logo and sidebar -->
<aside class="main-sidebar">
    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">
        <!-- Sidebar user panel -->
        <div class="user-panel">
            <div class="pull-left image">
                <img src="{{ url(auth()->user()->foto ?? '') }}" class="img-circle img-profil" alt="User Image">
            </div>
            <div class="pull-left info">
                <p>{{ auth()->user()->name }}</p>
                <a href="#"><i class="fa fa-circle text-success"></i> Online</a>
            </div>
        </div>
        
        <!-- /.search form -->
        <!-- sidebar menu: : style can be found in sidebar.less -->
        @php
            $routeName = Route::currentRouteName();
            $inventoryRoutes = [
                'produk.index', 'produk.incomplete', 'produk.updated', 'produk.out_of_stock', 'produk.sold_out_of_stock', 'produk.missing_supplier',
                // Purchase Orders - Currently Inactive
                // 'purchase-orders.index', 'purchase-orders.create', 'purchase-orders.receive-page',
                // 'purchase-orders.received.index'
            ];
            $shopRoutes = ['shop.index', 'shop.products', 'shop.products.data', 'shop.products.history', 'shop.products.history.print'];
            $supplierRoutes = [
                'supplier.index', 'supplier.withdrawal.index', 'supplier.withdrawal',
                'supplier.withdrawals'
            ];
            $incompleteCount = \App\Models\Produk::where('is_incomplete', true)->count();
            $pendingReceiptCount = \App\Models\Penjualan::receiptConfirmationBadgeCount();
            $consignmentRoutes = ['payment.pending', 'payment.index', 'payment.consignments'];
            $cashPaymentRoutes = ['payment.cash', 'payment.cash.history', 'payment.cash.sales-generated'];
            $posRoutes = ['transaksi.baru'];
            $salesRoutes = [
                'daily-cash.open-form', 'daily-cash.close-form',
                'transaksi.index', 'transaksi.suspended', 'transaksi.initiate_edit_form', 'penjualan.index',
                'penjualan.initiated_edits', 'penjualan.detailed-report', 'penjualan.no-supplier-sales', 'driver-commission.index',
                'receipt-confirmation.index', 'receipt-confirmation.show', 'receipt-confirmation.edit'
            ];
            $expenseRoutes = ['pengeluaran.index'];
            $payrollRoutes = [
                'payroll.dashboard', 'employee.index', 'payroll.index', 'payroll.settings'
            ];
            $reportRoutes = [
                'sales-report.summary', 'sales-report.detailed', 'laporan.index', 'report.product',
                'reports.supplier-payments', 'report.index', 'reports.kra-tax', 'reports.daily-closing', 'reports.purchase-orders', 'reports.salary-payments', 'reports.sales-by-shop',
                'account.index'
            ];
            $systemRoutes = ['user.index', 'setting.index', 'backup.index'];
        @endphp

        <ul class="sidebar-menu" data-widget="tree">
            <li>
                <a href="{{ route('dashboard') }}">
                    <i class="fa fa-dashboard"></i> <span>Dashboard</span>
                </a>
            </li>
            <li class="{{ (Route::currentRouteName() ?? '') === 'activities' ? 'active' : '' }}">
                <a href="{{ route('activities') }}">
                    <i class="fa fa-th-large"></i> <span>Activities</span>
                </a>
            </li>
            <li class="{{ (Route::currentRouteName() ?? '') === 'chat.index' ? 'active' : '' }}">
                <a href="{{ route('chat.index') }}">
                    <i class="fa fa-comments"></i> <span>Chat</span>
                    <span class="pull-right-container" id="chat-unread-wrap" style="{{ ($chatUnreadCount ?? 0) > 0 ? '' : 'display:none;' }}"><span class="label label-danger" id="chat-unread-badge">{{ $chatUnreadCount ?? 0 }}</span></span>
                </a>
            </li>

            @php($u = auth()->user())

            @if($u && ($u->hasModulePermission('inventory','read') || $u->hasRole('admin')))
            @php($inventoryOpen = in_array($routeName, $inventoryRoutes))
            <li class="treeview {{ $inventoryOpen ? 'menu-open active' : '' }}">
                <a href="#">
                    <i class="fa fa-archive"></i> <span>Stock</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu" style="{{ $inventoryOpen ? 'display:block;' : '' }}">
                    <li>
                        <a href="{{ route('produk.index') }}"><i class="fa fa-circle-o"></i> Enter Stock</a>
                    </li>
                    <li>
                        <a href="{{ route('produk.incomplete') }}">
                            <i class="fa fa-circle-o text-warning"></i> Quick Add
                            <span class="pull-right-container" style="margin-right: 10px;" id="incomplete-count-container">
                                @if($incompleteCount > 0)
                                    <small class="label label-warning" id="incomplete-count-badge">{{ $incompleteCount }}</small>
                                @endif
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('produk.updated') }}">
                            <i class="fa fa-circle-o text-info"></i>Stock History
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('produk.out_of_stock') }}">
                            <i class="fa fa-circle-o text-danger"></i> Out of Stock
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('produk.sold_out_of_stock') }}">
                            <i class="fa fa-circle-o text-warning"></i> Sold Out of Stock
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('produk.missing_supplier') }}">
                            <i class="fa fa-circle-o text-muted"></i> No Supplier
                        </a>
                    </li>
                    {{-- Purchase Orders - Currently Inactive
                    <li><a href="{{ route('purchase-orders.index') }}"><i class="fa fa-circle-o"></i> Purchase Orders</a></li>
                    <li><a href="{{ route('purchase-orders.create') }}"><i class="fa fa-circle-o"></i> New Purchase Order</a></li>
                    <li><a href="{{ route('purchase-orders.receive-page') }}"><i class="fa fa-circle-o"></i> Receive Orders</a></li>
                    <li><a href="{{ route('purchase-orders.received.index') }}"><i class="fa fa-circle-o"></i> Received Orders</a></li>
                    --}}
                </ul>
            </li>
            @endif

            @if($u && ($u->hasModulePermission('inventory','read') || $u->hasRole('admin')))
            @php($shopOpen = in_array($routeName, $shopRoutes))
            <li class="treeview {{ $shopOpen ? 'menu-open active' : '' }}">
                <a href="#">
                    <i class="fa fa-building"></i> <span>Shops</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu" style="{{ $shopOpen ? 'display:block;' : '' }}">
                    <li><a href="{{ route('shop.index') }}"><i class="fa fa-circle-o"></i> Shop List</a></li>
                </ul>
            </li>
            @endif

            @if($u && ($u->hasModulePermission('inventory','read') || $u->hasRole('admin')))
            @php($supplierOpen = in_array($routeName, $supplierRoutes))
            <li class="treeview {{ $supplierOpen ? 'menu-open active' : '' }}">
                <a href="#">
                    <i class="fa fa-truck"></i> <span>Suppliers</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu" style="{{ $supplierOpen ? 'display:block;' : '' }}">
                    <li><a href="{{ route('supplier.index') }}"><i class="fa fa-circle-o"></i> Supplier List</a></li>
                    <li><a href="{{ route('supplier.withdrawal.index') }}"><i class="fa fa-circle-o"></i> Supplier Withdrawal</a></li>
                    <li><a href="{{ route('supplier.withdrawals') }}"><i class="fa fa-circle-o"></i> Withdrawals List</a></li>
                </ul>
            </li>
            @endif

            @if($u && ($u->hasModulePermission('consignment','read') || $u->hasRole('admin')))
            @php($consignmentOpen = in_array($routeName, $consignmentRoutes))
            <li class="treeview {{ $consignmentOpen ? 'menu-open active' : '' }}">
                <a href="#">
                    <i class="fa fa-handshake-o"></i> <span>Consignment</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu" style="{{ $consignmentOpen ? 'display:block;' : '' }}">
                    <li><a href="{{ route('payment.consignments') }}"><i class="fa fa-circle-o"></i> All Consignments</a></li>
                    <li><a href="{{ route('payment.pending') }}"><i class="fa fa-circle-o"></i> Consignment List</a></li>
                    <li><a href="{{ route('payment.index') }}"><i class="fa fa-circle-o"></i> Paid Consignments</a></li>
                </ul>
            </li>
            @endif

            @if($u && ($u->hasModulePermission('consignment','read') || $u->hasRole('admin')))
            @php($cashPaymentOpen = in_array($routeName, $cashPaymentRoutes))
            <li class="treeview {{ $cashPaymentOpen ? 'menu-open active' : '' }}">
                <a href="#">
                    <i class="fa fa-money"></i> <span>Cash Sale</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu" style="{{ $cashPaymentOpen ? 'display:block;' : '' }}">
                    <li><a href="{{ route('payment.cash.sales-generated') }}"><i class="fa fa-circle-o"></i> Cash Generated Sale</a></li>
                </ul>
            </li>
            @endif

            @if($u && ($u->hasModulePermission('sales','read') || $u->hasRole('admin')))
            @php($posOpen = in_array($routeName, $posRoutes))
            <li class="{{ $posOpen ? 'active' : '' }}">
                <a href="{{ route('transaksi.baru') }}">
                    <i class="fa fa-shopping-bag"></i> <span>POS</span>
                </a>
            </li>
            @endif

            @if($u && ($u->hasModulePermission('sales','read') || $u->hasRole('admin')))
            @php($salesOpen = in_array($routeName, $salesRoutes))
            <li class="treeview {{ $salesOpen ? 'menu-open active' : '' }}">
                <a href="#">
                    <i class="fa fa-shopping-cart"></i> <span>Sales Operations</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu" style="{{ $salesOpen ? 'display:block;' : '' }}">
                    <li><a href="{{ route('daily-cash.open-form') }}"><i class="fa fa-circle-o"></i> Open Day</a></li>
                    <li><a href="{{ route('daily-cash.close-form') }}"><i class="fa fa-circle-o"></i> Close Day</a></li>
                    <li><a href="{{ route('penjualan.management.index') }}"><i class="fa fa-circle-o"></i> Taken by Management</a></li>
                    <li><a href="{{ route('transaksi.index') }}"><i class="fa fa-circle-o"></i> Active Daily Sale</a></li>
                    <li><a href="{{ route('transaksi.suspended') }}"><i class="fa fa-circle-o"></i> Suspended Sales</a></li>
                    <li><a href="{{ route('transaksi.initiate_edit_form') }}"><i class="fa fa-circle-o"></i> Find sale to edit (receipt)</a></li>
                    <li><a href="{{ route('penjualan.initiated_edits') }}"><i class="fa fa-circle-o"></i> Initiated Sale Edits</a></li>
                    @if($u && $u->hasRole('admin'))
                    <li><a href="{{ route('penjualan.index') }}"><i class="fa fa-circle-o"></i> Sales List</a></li>
                    <li><a href="{{ route('penjualan.detailed-report') }}"><i class="fa fa-circle-o"></i>Today Sales Report</a></li>
                    <li><a href="{{ route('penjualan.no-supplier-sales') }}"><i class="fa fa-circle-o text-muted"></i> No Supplier Sales</a></li>
                    <li>
                        <a href="{{ route('receipt-confirmation.index') }}">
                            <i class="fa fa-circle-o"></i> Receipt Confirmation
                            <span class="pull-right-container receipt-confirmation-badge-wrap" style="{{ $pendingReceiptCount > 0 ? '' : 'display:none;' }}">
                                <small class="label label-warning receipt-confirmation-badge-count">{{ $pendingReceiptCount }}</small>
                            </span>
                        </a>
                    </li>
                    @endif
                    <li><a href="{{ route('driver-commission.index') }}"><i class="fa fa-circle-o"></i> Service Fee</a></li>
                </ul>
            </li>
            @endif

            @if($u && ($u->hasModulePermission('expense','read') || $u->hasRole('admin')))
            @php($expenseOpen = in_array($routeName, $expenseRoutes))
            <li class="treeview {{ $expenseOpen ? 'menu-open active' : '' }}">
                <a href="#">
                    <i class="fa fa-money"></i> <span>Expenses</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu" style="{{ $expenseOpen ? 'display:block;' : '' }}">
                    <li><a href="{{ route('pengeluaran.index') }}"><i class="fa fa-circle-o"></i> Expense List</a></li>
                </ul>
            </li>
            @endif

            @if($u && ($u->hasModulePermission('payroll','read') || $u->hasRole('admin')))
            @php($payrollOpen = in_array($routeName, $payrollRoutes))
            <li class="treeview {{ $payrollOpen ? 'menu-open active' : '' }}">
                <a href="#">
                    <i class="fa fa-briefcase"></i> <span>Payroll & HR</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu" style="{{ $payrollOpen ? 'display:block;' : '' }}">
                    <li><a href="{{ route('payroll.dashboard') }}"><i class="fa fa-circle-o"></i> Payroll Dashboard</a></li>
                    <li><a href="{{ route('employee.index') }}"><i class="fa fa-circle-o"></i> Employees</a></li>
                    <li><a href="{{ route('payroll.index') }}"><i class="fa fa-circle-o"></i> Payroll</a></li>
                    <li><a href="{{ route('payroll.settings') }}"><i class="fa fa-circle-o"></i> Tax Settings</a></li>
                </ul>
            </li>

            @php($reportOpen = in_array($routeName, $reportRoutes))
            <li class="treeview {{ $reportOpen ? 'menu-open active' : '' }}">
                <a href="#">
                    <i class="fa fa-bar-chart"></i> <span>Reports</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu" style="{{ $reportOpen ? 'display:block;' : '' }}">
                    <li><a href="{{ route('sales-report.summary') }}"><i class="fa fa-circle-o"></i> Sales Summary</a></li>
                    <li><a href="{{ route('sales-report.detailed') }}"><i class="fa fa-circle-o"></i> Sales Detailed</a></li>
                    <li><a href="{{ route('laporan.index') }}"><i class="fa fa-circle-o"></i> Income Report</a></li>
                    <li><a href="{{ route('report.product') }}"><i class="fa fa-circle-o"></i> Product Report</a></li>
                    <li><a href="{{ route('reports.supplier-payments') }}"><i class="fa fa-circle-o"></i> Supplier Payments</a></li>
                    <li><a href="{{ route('report.index') }}"><i class="fa fa-circle-o"></i> Sales Reports</a></li>
                    <li><a href="{{ route('reports.kra-tax') }}"><i class="fa fa-circle-o"></i> KRA Tax Report</a></li>
                    <li><a href="{{ route('reports.daily-closing') }}"><i class="fa fa-circle-o"></i> Daily Closing</a></li>
                    {{-- Purchase Orders Report - Currently Inactive
                    <li><a href="{{ route('reports.purchase-orders') }}"><i class="fa fa-circle-o"></i> Purchase Orders Report</a></li>
                    --}}
                    <li><a href="{{ route('reports.salary-payments') }}"><i class="fa fa-circle-o"></i> Salary Payment Report</a></li>
                    <li><a href="{{ route('account.index') }}"><i class="fa fa-circle-o"></i> Accounts</a></li>
                    <li><a href="{{ route('reports.sales-by-shop') }}"><i class="fa fa-circle-o"></i> Sales by Shop</a></li>
                </ul>
            </li>
            @endif

            @if($u && ($u->hasRole('admin') || $u->can_read))
            @php($systemOpen = in_array($routeName, $systemRoutes))
            <li class="treeview {{ $systemOpen ? 'menu-open active' : '' }}">
                <a href="#">
                    <i class="fa fa-cogs"></i> <span>System</span>
                    <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
                </a>
                <ul class="treeview-menu" style="{{ $systemOpen ? 'display:block;' : '' }}">
                    <li><a href="{{ route('user.index') }}"><i class="fa fa-circle-o"></i> Users</a></li>
                    @if($u && $u->hasRole('admin'))
                    <li><a href="{{ route('setting.index') }}"><i class="fa fa-circle-o"></i> Settings</a></li>
                    <li><a href="{{ route('backup.index') }}"><i class="fa fa-circle-o"></i> Backup</a></li>
                    @endif
                </ul>
            </li>
            @endif
        </ul>
    </section>
    <!-- /.sidebar -->
</aside><!-- visit "codeastro" for more projects! -->
