@props(['content'])
@php
  $s = $content->status;
  $tone = match ($s) { 'published', 'corrected' => 'green', 'pending_approval', 'scheduled' => 'blue', 'changes_requested' => 'amber', 'rejected' => 'red', default => 'neutral' };
  $label = \App\Models\Story::STATUSES[$s] ?? ucfirst(str_replace('_', ' ', $s));
  if ($s === 'scheduled' && $content->publish_attempts > 0) { $tone = 'red'; $label = 'Failed to publish'; }
@endphp
<span class="pill {{ $tone }}">{{ $label }}</span>
