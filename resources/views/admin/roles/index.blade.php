@extends('admin.layouts.app')
@section('title', 'Roles & permissions')
@section('content')
<div x-data="{open:false}">
<x-admin.ph title="Roles & permissions" sub="A role is a set of permissions. Permissions marked * are sensitive: they need password confirmation and are always audit-logged.">
  <button class="btn primary" @click="open=true">{!! icon('plus', 14) !!} New role</button>
</x-admin.ph>
<div class="card"><table class="t"><thead><tr><th>Role</th><th class="num">Permissions</th><th class="num">Admins</th><th></th></tr></thead><tbody>
@foreach($roles as $r)<tr><td><a class="t1" href="{{ route('admin.roles.edit', $r) }}">{{ $r->name }}</a>@if(in_array($r->name, \App\Http\Controllers\Admin\RoleController::SYSTEM)) <span class="pill">built-in</span>@endif</td>
  <td class="num">{{ $r->name === 'Super Admin' ? 'All' : $r->permissions_count }}</td><td class="num"><a href="{{ route('admin.team.index', ['role' => $r->name]) }}">{{ $r->users_count }}</a></td>
  <td class="right"><a class="btn xs" href="{{ route('admin.roles.edit', $r) }}">{{ $r->name === 'Super Admin' ? 'View' : 'Edit permissions' }}</a></td></tr>@endforeach
</tbody></table></div>
<template x-if="open"><div class="overlay" @click.self="open=false"><form class="modal" method="post" action="{{ route('admin.roles.store') }}">@csrf
  <div class="modal-h"><h2>New role</h2></div><div class="modal-b">
    <x-admin.field label="Role name" name="name" required><input name="name" required></x-admin.field>
    <x-admin.field label="Start from" help="Copies that role's permissions."><select name="copy_from"><option value="">Nothing</option>@foreach($roles as $r)@if($r->name !== 'Super Admin')<option value="{{ $r->id }}">{{ $r->name }}</option>@endif @endforeach</select></x-admin.field>
    <x-admin.field label="Your password" name="confirm_password" help="Creating roles is a sensitive action."><input type="password" name="confirm_password" required></x-admin.field>
  </div><div class="modal-f"><button type="button" class="btn" @click="open=false">Cancel</button><button class="btn primary">Create</button></div></form></div></template>
</div>
@endsection
