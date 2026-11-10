@props(['config', 'size' => ''])
<div class="chart-box {{ $size }}" data-chart="{{ json_encode($config) }}" role="img" aria-label="{{ $config['aria'] ?? 'Chart' }}"></div>
