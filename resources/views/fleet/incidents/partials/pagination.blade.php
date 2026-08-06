@if ($paginator->hasPages())
    <nav class="fleet-incident-pager" aria-label="Incident pagination">
        @if ($paginator->onFirstPage())
            <span class="fleet-incident-page-btn disabled">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="fleet-incident-page-btn">Previous</a>
        @endif

        @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
            @if ($page == $paginator->currentPage())
                <span class="fleet-incident-page-btn active">{{ $page }}</span>
            @else
                <a href="{{ $url }}" class="fleet-incident-page-btn">{{ $page }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="fleet-incident-page-btn">Next</a>
        @else
            <span class="fleet-incident-page-btn disabled">Next</span>
        @endif
    </nav>
@endif
