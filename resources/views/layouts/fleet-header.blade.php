<header class="fleet-topbar">
    <div class="fleet-topbar-left">
        <button type="button" class="fleet-icon-btn" id="fleet-sidebar-toggle" aria-label="Toggle menu">
            <i class="fa fa-bars"></i>
        </button>

        @if(empty($hideWorkspaceChrome) && empty($ownerConsole))
        <div class="dropdown" style="display:inline-block;">
            <button type="button" class="sx-header-btn dropdown-toggle" data-toggle="dropdown">
                +Links
                <span class="caret"></span>
            </button>
            <ul class="dropdown-menu sx-links-menu">
                <li><a href="{{ route('pos.index') }}">POS</a></li>
                <li><a href="#">Purchase</a></li>
                <li><a href="#">Customer</a></li>
                <li><a href="#">Supplier</a></li>
                <li><a href="{{ route('products.create') }}">Item</a></li>
                <li><a href="#">Expense</a></li>
            </ul>
        </div>
        @endif
    </div>

    <div class="fleet-topbar-right">
        @if(!empty($branches) && $branches->count())
            @if(auth()->user()->canSwitchBranches())
                <form action="{{ route('branch.switch') }}" method="post" style="margin:0;">
                    @csrf
                    <select name="branch_id" class="sx-header-select" onchange="this.form.submit()" title="Branch">
                        @foreach($branches as $headerBranch)
                            <option value="{{ $headerBranch->id }}" @if(!empty($branch) && (int) $branch->id === (int) $headerBranch->id) selected @endif>
                                {{ $headerBranch->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @else
                <span class="sx-header-select" title="Branch" style="display:inline-flex;align-items:center;padding:0 10px;">
                    {{ optional($branch)->name ?? 'Branch' }}
                </span>
            @endif
        @endif

        @if(empty($hideWorkspaceChrome) && empty($ownerConsole))
        <a href="{{ route('pos.index') }}" class="sx-header-btn" title="POS">
            <i class="fa fa-shopping-cart"></i> POS
        </a>
        @endif

        @php
            $stockAlertCount = (int) ($notificationCount ?? 0);
            $stockAlertItems = $outOfStockNotifications ?? collect();
            $canOpenStockAlert = empty($ownerConsole) && empty($hideWorkspaceChrome) && auth()->check() && (
                auth()->user()->hasPermission('inventory.view')
                || auth()->user()->hasPermission('products.view')
                || auth()->user()->hasPermission('pos.view')
            );
        @endphp
        @if($canOpenStockAlert)
            <div class="dropdown sx-notify-wrap" style="display:inline-block;">
                <button type="button" class="sx-header-btn dropdown-toggle sx-notify-btn" data-toggle="dropdown" title="Out of stock">
                    <i class="fa fa-bell"></i>
                    @if($stockAlertCount > 0)
                        <span class="sx-notify-badge">{{ $stockAlertCount > 99 ? '99+' : $stockAlertCount }}</span>
                    @endif
                </button>
                <ul class="dropdown-menu dropdown-menu-right sx-notify-menu">
                    <li class="sx-notify-header">
                        @if($stockAlertCount > 0)
                            {{ $stockAlertCount }} item{{ $stockAlertCount === 1 ? '' : 's' }} out of stock
                            @if(!empty($branch)) at {{ $branch->name }}@endif
                        @else
                            No items out of stock
                            @if(!empty($branch)) at {{ $branch->name }}@endif
                        @endif
                    </li>
                    @forelse($stockAlertItems as $item)
                        <li>
                            <a href="{{ route('products.show', $item->id) }}">
                                <i class="fa fa-cube text-danger"></i>
                                <span class="sx-notify-item-name">{{ $item->name }}</span>
                                <span class="sx-notify-item-meta">Qty {{ number_format((float) $item->quantity, 2) }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="sx-notify-empty"><span>Stock levels look fine for this branch.</span></li>
                    @endforelse
                    @if(auth()->user()->hasPermission('inventory.view'))
                        <li class="sx-notify-footer">
                            <a href="{{ route('stock.alert') }}">View stock alerts</a>
                        </li>
                    @endif
                </ul>
            </div>
        @endif

        @if(!empty($ownerConsole))
        <a href="{{ route('owner.dashboard') }}" class="sx-header-btn {{ ($activeMenu ?? '') === 'owner.dashboard' ? 'is-active' : '' }}">
            Owner
        </a>
        @elseif(empty($hideWorkspaceChrome))
        <a href="{{ route('dashboard') }}" class="sx-header-btn {{ ($activeMenu ?? '') === 'dashboard' ? 'is-active' : '' }}">
            Dashboard
        </a>
        @endif
        <form action="{{ route('logout') }}" method="post" style="display:inline;margin:0;">
            @csrf
            <button type="submit" class="sx-logout-btn">LOGOUT</button>
        </form>
        <span class="sx-header-user">
            <i class="fa fa-user-circle"></i>
            {{ auth()->user()->name }}
        </span>
    </div>
</header>
