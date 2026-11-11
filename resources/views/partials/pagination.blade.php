@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Pagination">
        <span>Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
        <div class="pages">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm btn-outline" aria-disabled="true" style="opacity:.5">Previous</span>
            @else
                <a class="btn btn-sm btn-outline" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif
            <span class="btn btn-sm btn-outline" style="pointer-events:none">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
            @if ($paginator->hasMorePages())
                <a class="btn btn-sm btn-outline" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="btn btn-sm btn-outline" aria-disabled="true" style="opacity:.5">Next</span>
            @endif
        </div>
    </nav>
@endif
