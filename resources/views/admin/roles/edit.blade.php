@extends('admin.layouts.app')
@section('title', $role->name)
@section('content')
@php $has = $role->permissions->pluck('name')->all(); $super = $role->name === 'Super Admin'; @endphp
<x-admin.ph :title="$role->name" :crumbs="['Roles & permissions' => route('admin.roles.index')]" :sub="$super ? 'Super Admin always has every permission.' : count($has).' permissions'">
  @unless($system)<form method="post" action="{{ route('admin.roles.destroy', $role) }}" data-confirm="Delete the role “{{ $role->name }}”?" data-confirm-password data-confirm-danger>@csrf @method('delete')<button class="btn danger">Delete role</button></form>@endunless
</x-admin.ph>
<form method="post" action="{{ route('admin.roles.update', $role) }}" data-confirm="Save permissions for {{ $role->name }}?" data-confirm-detail="Admins in this role get the new permissions on their next request." data-confirm-password>@csrf @method('put')
  <div class="grid g3">
    @foreach($groups as $group => $perms)
      <div class="card"><div class="card-h"><h2>{{ $group }}</h2>@unless($super)<button type="button" class="link-btn small" onclick="this.closest('.card').querySelectorAll('input[type=checkbox]').forEach(c=>c.checked=true)">All</button>@endunless</div>
        <div class="card-b stack-sm">@foreach($perms as $name => $label)<label class="cb"><input type="checkbox" name="permissions[]" value="{{ $name }}" @checked($super || in_array($name, $has)) @disabled($super)> <span>{{ $label }}<br><code class="xsmall muted">{{ $name }}</code></span></label>@endforeach</div></div>
    @endforeach
  </div>
  @unless($super)<div class="row mt-4"><button class="btn primary">Save permissions</button></div>@endunless
</form>
@endsection
