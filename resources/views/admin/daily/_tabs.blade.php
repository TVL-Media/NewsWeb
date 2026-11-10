<nav class="tabs">
  <a href="{{ route('admin.daily.briefings') }}" class="{{ request()->routeIs('admin.daily.briefing*') ? 'on' : '' }}">Morning 5</a>
  <a href="{{ route('admin.daily.quizzes') }}" class="{{ request()->routeIs('admin.daily.quiz*') ? 'on' : '' }}">Daily quiz</a>
</nav>
