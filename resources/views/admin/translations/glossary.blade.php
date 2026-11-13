@extends('admin.layouts.app')
@section('title', 'Glossary')
@section('content')
<x-admin.ph title="Translations" sub="Glossary terms are sent with every translation request so names and terms stay consistent." />
@include('admin.translations._tabs')
<div class="grid g-main">
  <div class="card">
    <form class="filters" method="get"><select name="language" style="width:auto" onchange="this.form.submit()"><option value="">Both languages</option><option value="hi" @selected(request('language')==='hi')>Into Hindi</option><option value="en" @selected(request('language')==='en')>Into English</option></select></form>
    <table class="t"><thead><tr><th>Language</th><th>Source term</th><th>Translation</th><th></th></tr></thead><tbody>
    @forelse($terms as $t)<tr><td class="small">{{ $t->language_code === 'hi' ? 'Into Hindi' : 'Into English' }}</td><td>{{ $t->source_term }}</td><td>{!! $t->do_not_translate ? '<span class="pill">keep as is</span>' : e($t->target_term) !!}</td>
      <td class="right"><form method="post" action="{{ route('admin.glossary.destroy', $t) }}">@csrf @method('delete')<button class="btn xs ghost">Remove</button></form></td></tr>
    @empty <tr><td colspan="4"><x-admin.empty title="No glossary terms">Add party names, people, places and terms that must be translated the same way every time.</x-admin.empty></td></tr> @endforelse
    </tbody></table>{{ $terms->links() }}
  </div>
  <form class="card" method="post" action="{{ route('admin.glossary.store') }}" x-data="{k:false}">@csrf
    <div class="card-h"><h2>Add term</h2></div><div class="card-b">
      <x-admin.field label="Language" name="language_code"><select name="language_code"><option value="hi">Into Hindi</option><option value="en">Into English</option></select></x-admin.field>
      <x-admin.field label="Source term" name="source_term" required><input name="source_term" required></x-admin.field>
      <label class="cb mb-3"><input type="checkbox" name="do_not_translate" value="1" x-model="k"> Do not translate (keep as is)</label>
      <div x-show="!k"><x-admin.field label="Translation" name="target_term"><input name="target_term"></x-admin.field></div>
    </div><div class="card-f"><button class="btn primary">Add</button></div>
  </form>
</div>
@endsection
