<ul class="checks">
  @foreach ($rows as $r)
    <li>
      <span class="dot {{ $r['ok'] ? 'ok' : ($r['required'] ? 'no' : 'warn') }}" aria-hidden="true">{{ $r['ok'] ? '✓' : ($r['required'] ? '✕' : '!') }}</span>
      <div>
        <div class="name">{{ $r['name'] }}</div>
        <div class="note">{{ $r['ok'] ? '' : ($r['required'] ? 'Missing. ' : 'Optional. ') }}{{ $r['note'] }}</div>
      </div>
    </li>
  @endforeach
</ul>
