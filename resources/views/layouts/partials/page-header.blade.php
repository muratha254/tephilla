<div class="sx-content-header">
    <div class="sx-content-title">
        <a href="{{ $backUrl ?? route('dashboard') }}" class="sx-back-btn" aria-label="Back"><i class="fa fa-chevron-left"></i></a>
        <div>
            <h1>{{ $title }}</h1>
            @if(!empty($subtitle))
                <small>{{ $subtitle }}</small>
            @endif
        </div>
    </div>
    <div class="sx-content-right">
        @if(!empty($headerAction['url'] ?? null))
            <a href="{{ $headerAction['url'] }}" class="btn sx-btn-aqua {{ $headerAction['class'] ?? '' }}">
                @if(!empty($headerAction['icon']))<i class="fa {{ $headerAction['icon'] }}"></i> @endif
                {{ $headerAction['label'] ?? '' }}
            </a>
        @endif
        @if(!empty($breadcrumbs))
            <ol class="sx-breadcrumb">
                @foreach($breadcrumbs as $crumb)
                    @if(!empty($crumb['url']))
                        <li>
                            <a href="{{ $crumb['url'] }}">
                                @if(!empty($crumb['icon']))<i class="fa {{ $crumb['icon'] }}"></i> @endif{{ $crumb['label'] }}
                            </a>
                        </li>
                    @else
                        <li class="active">{{ $crumb['label'] }}</li>
                    @endif
                @endforeach
            </ol>
        @else
            <a href="{{ route('dashboard') }}" class="sx-home-link"><i class="fa fa-home"></i> Home</a>
        @endif
    </div>
</div>
