<nav class="tabs">
  <a href="{{ route('admin.translations.index') }}" class="{{ request()->routeIs('admin.translations.index') ? 'on' : '' }}">Translation queue</a>
  @can('translation.settings')<a href="{{ route('admin.translations.settings') }}" class="{{ request()->routeIs('admin.translations.settings') ? 'on' : '' }}">Settings</a>
  <a href="{{ route('admin.glossary.index') }}" class="{{ request()->routeIs('admin.glossary.*') ? 'on' : '' }}">Glossary</a>@endcan
</nav>
