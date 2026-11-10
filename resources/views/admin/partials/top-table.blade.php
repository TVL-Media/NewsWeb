@if(empty($rows))
  <x-admin.empty title="No activity in this period">Stories appear here once readers start reading them.</x-admin.empty>
@else
<table class="t compact"><thead><tr><th>Story</th><th>Category</th><th class="num">{{ $colLabel }}</th></tr></thead><tbody>
@foreach($rows as $r)
  <tr><td><a href="{{ route('admin.stories.edit', $r['id']) }}" class="t1">{{ \Illuminate\Support\Str::limit($r['title'], 70) }}</a>@if($r['mode'] === 'auto') <span class="pill blue">Auto</span>@endif</td><td class="small muted">{{ $r['category'] }}</td><td class="num strong">{{ num($r['value']) }}</td></tr>
@endforeach
</tbody></table>
@endif
