<nav class="tabs">
  @can('feeds.manage')<a href="{{ route('admin.newsroom.feeds') }}" class="{{ request()->routeIs('admin.newsroom.feeds') ? 'on' : '' }}">Feeds &amp; sources</a>@endcan
  @can('autopublish.manage')<a href="{{ route('admin.newsroom.autopublish') }}" class="{{ request()->routeIs('admin.newsroom.autopublish') ? 'on' : '' }}">Auto-publish rules</a>@endcan
  <a href="{{ route('admin.newsroom.log') }}" class="{{ request()->routeIs('admin.newsroom.log') ? 'on' : '' }}">Auto-publish log</a>
</nav>
