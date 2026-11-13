@props(['label' => null, 'name' => null, 'required' => false, 'help' => null])
<div {{ $attributes->merge(['class' => 'field'.($name && $errors->has($name) ? ' has-err' : '')]) }}>
  @if($label)<label @if($name) for="f-{{ str_replace(['[',']','.'], '-', $name) }}" @endif>{{ $label }} @if($required)<span class="req">*</span>@endif</label>@endif
  {{ $slot }}
  @if($help)<div class="help">{{ $help }}</div>@endif
  @if($name)@error($name)<div class="err">{{ $message }}</div>@enderror @endif
</div>
