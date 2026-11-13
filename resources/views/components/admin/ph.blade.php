@props(['title', 'sub' => null, 'crumbs' => []])
<div class="ph">
  <div>
    @if($crumbs)<div class="crumbs">@foreach($crumbs as $label => $url)<a href="{{ $url }}">{{ $label }}</a> / @endforeach</div>@endif
    <h1>{{ $title }}</h1>
    @if($sub)<div class="sub">{{ $sub }}</div>@endif
  </div>
  <div class="ph-actions">{{ $slot }}</div>
</div>
