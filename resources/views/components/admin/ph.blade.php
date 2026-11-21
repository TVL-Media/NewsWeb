@props(['title', 'sub' => null, 'crumbs' => [], 'back' => null])
@php
  // Back: the parent page when the page has crumbs (keeps working after a save), otherwise the previous
  // page in this tab (falls back to the dashboard). Not shown on the dashboard itself.
  $backUrl = $back ?? ($crumbs ? end($crumbs) : route('admin.dashboard'));
  $showBack = $back !== false && ! request()->routeIs('admin.dashboard');
@endphp
<div class="ph">
  <div>
    @if($showBack)
      <a href="{{ $backUrl }}" class="ph-back" @unless($crumbs || $back) data-history-back @endunless>{!! icon('arrow-left', 14) !!} Back</a>
    @endif
    @if($crumbs)<div class="crumbs">@foreach($crumbs as $label => $url)<a href="{{ $url }}">{{ $label }}</a> / @endforeach</div>@endif
    <h1>{{ $title }}</h1>
    @if($sub)<div class="sub">{{ $sub }}</div>@endif
  </div>
  <div class="ph-actions">{{ $slot }}</div>
</div>
