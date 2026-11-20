@php $prev = $steps[array_search($step, $steps, true) - 1] ?? null; @endphp
@if ($prev)<a class="btn btn-outline" href="{{ route('install.step', $prev) }}">← Back</a>@else<span></span>@endif
