@if ($paginator->hasPages() || $paginator->total() > 0)
<div class="pager">
  <span>Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ number_format($paginator->total()) }}</span>
  <div class="row">
    <form method="get" class="row">
      @foreach(request()->except(['per_page','page']) as $k => $v) @if(!is_array($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
      <select name="per_page" onchange="this.form.submit()" style="height:30px;width:auto" aria-label="Rows per page">
        @foreach([25,50,100] as $pp)<option value="{{ $pp }}" @selected(request('per_page',25)==$pp)>{{ $pp }} / page</option>@endforeach
      </select>
    </form>
    @if($paginator->hasPages())
    <nav aria-label="Pagination">
      @if ($paginator->onFirstPage())<span class="pg dis">‹</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a>@endif
      @foreach ($elements as $element)
        @if (is_string($element))<span class="pg dis">…</span>@endif
        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())<span class="pg on">{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif
          @endforeach
        @endif
      @endforeach
      @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">›</a>@else<span class="pg dis">›</span>@endif
    </nav>
    @endif
  </div>
</div>
@endif
