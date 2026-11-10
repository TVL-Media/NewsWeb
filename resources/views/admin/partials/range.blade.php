{{-- Date-range filter (PRD §3, §12.6). --}}
<form method="get" class="row" x-data="{ p: '{{ $preset }}' }">
  @foreach(request()->except(['range','from','to','page']) as $k => $v) @if(!is_array($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
  <select name="range" x-model="p" @change="if(p!=='custom') $el.form.submit()" style="width:auto" aria-label="Date range">
    <option value="today">Today</option><option value="7d">Last 7 days</option><option value="30d">Last 30 days</option><option value="month">This month</option><option value="90d">Last 90 days</option><option value="custom">Custom…</option>
  </select>
  <template x-if="p==='custom'"><span class="row"><input type="date" name="from" value="{{ $from }}" style="width:auto"><input type="date" name="to" value="{{ $to }}" style="width:auto"><button class="btn sm">Apply</button></span></template>
</form>
