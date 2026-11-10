@extends('admin.layouts.app')
@section('title', 'Schedule')
@push('head')<script src="{{ asset('vendor/fullcalendar.min.js') }}"></script>@endpush
@section('content')
<x-admin.ph title="Schedule" :sub="'Scheduled stories publish automatically every minute. Grey = auto-published by the newsroom pipeline. All times '.tz_label().'.'">
  <div class="btn-group"><a class="btn sm {{ $view==='calendar' ? 'primary' : '' }}" href="{{ route('admin.schedule.index') }}">Calendar</a><a class="btn sm {{ $view==='list' ? 'primary' : '' }}" href="{{ route('admin.schedule.index', ['view' => 'list']) }}">List</a></div>
</x-admin.ph>
<div class="legend mb-3"><span><i style="background:#245BA8"></i>Scheduled</span><span><i style="background:#C8102E"></i>Failed to publish</span><span><i style="background:#1E7B4E"></i>Published</span></div>
@if($view === 'calendar' && ! request('failed'))
  <div class="card"><div class="card-b"><div id="cal"></div></div></div>
  @push('scripts')
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const cal = new FullCalendar.Calendar(document.getElementById('cal'), {
      initialView: 'dayGridMonth', height: 'auto', firstDay: 1, nowIndicator: true, timeZone: '{{ ptz() }}',
      headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
      eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: {{ setting('datetime.time_format') === '12h' ? 'true' : 'false' }} },
      events: '{{ route('admin.schedule.events') }}',
      eventDrop: info => {
        const ov = document.createElement('div'); ov.className = 'overlay';
        ov.innerHTML = `<div class="modal"><div class="modal-h"><h2>Reschedule?</h2></div><div class="modal-b"><p></p></div><div class="modal-f"><button class="btn" data-a="n">Cancel</button><button class="btn primary" data-a="y">Reschedule</button></div></div>`;
        ov.querySelector('p').textContent = `Move “${info.event.title}” to ${info.event.start.toLocaleString()}?`;
        document.body.appendChild(ov);
        ov.querySelector('[data-a=n]').onclick = () => { info.revert(); ov.remove(); };
        ov.querySelector('[data-a=y]').onclick = () => {
          ov.remove();
          fetch(`{{ url('admin/schedule') }}/${info.event.id}/move`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' }, body: JSON.stringify({ start: info.event.start.toISOString() }) })
            .then(r => r.json().then(j => ({ ok: r.ok, j }))).then(({ ok, j }) => { if (ok) toast('Rescheduled to ' + j.label); else { toast(j.message || 'Could not reschedule', 'err'); info.revert(); } });
        };
      },
    });
    cal.render();
  });
  </script>
  @endpush
@else
  <div class="card">
    <div class="filters">@if(request('failed'))<span class="pill red">Showing failed only</span> <a href="{{ route('admin.schedule.index', ['view' => 'list']) }}" class="small">Show all</a>@else<a href="{{ route('admin.schedule.index', ['view' => 'list', 'failed' => 1]) }}" class="small">Show only failed</a>@endif</div>
    <table class="t"><thead><tr><th>Goes live</th><th>Headline</th><th>Category</th><th>By</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse($list as $c)
      <tr><td class="nowrap mono small">{{ fmt_dt($c->scheduled_at) }}</td><td><a class="t1" href="{{ route('admin.stories.edit', $c) }}">{{ \Illuminate\Support\Str::limit($c->title(), 70) }}</a>@if($c->publish_attempts > 0)<div class="t2 red">{{ $c->last_publish_error }}</div>@endif</td>
        <td class="small">{{ $c->category?->name_en }}</td><td class="small">{{ $c->author?->name ?? '—' }}</td><td><x-admin.status :content="$c" /></td>
        <td class="right nowrap">@can('content.schedule')<form method="post" action="{{ route('admin.stories.schedule.cancel', $c) }}" style="display:inline" data-confirm="Cancel this schedule?" data-confirm-detail="The story goes back to draft.">@csrf<button class="btn xs">Cancel</button></form>@endcan
          @can('content.publish')<form method="post" action="{{ route('admin.stories.publish', $c) }}" style="display:inline">@csrf<button class="btn xs">Publish now</button></form>@endcan</td></tr>
    @empty <tr><td colspan="6"><x-admin.empty title="Nothing scheduled">Schedule a story from the editor or while approving it.</x-admin.empty></td></tr> @endforelse
    </tbody></table>{{ $list->links() }}
  </div>
@endif
<div class="card mt-4"><div class="card-h"><h2>Recently published</h2><span class="meta">last 48 hours</span></div><div class="card-b flush"><table class="t compact"><tbody>
  @forelse($recent as $c)<tr><td class="mono small nowrap">{{ fmt_dt($c->published_at) }}</td><td><a href="{{ route('admin.stories.edit', $c) }}">{{ $c->title() }}</a></td><td class="small muted">{{ $c->publish_mode === 'auto' ? 'Auto' : 'Editor' }}</td></tr>
  @empty <tr><td class="muted">Nothing published in the last 48 hours.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
