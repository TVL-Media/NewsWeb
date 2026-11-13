<?php

/*
| Admin panel (/admin): newsroom staff on the `admin` guard. Loaded from routes/web.php.
| Models whose website URL uses a slug (stories, pages, categories) are bound by id here.
*/

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

// ---------- Guest (sign in, password reset, invites) ----------
Route::prefix('admin')->name('admin.')->middleware('guest:admin')->group(function () {
    Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:admin-login')->name('login.attempt');
    Route::get('forgot-password', [Admin\AuthController::class, 'forgotForm'])->name('password.request');
    Route::post('forgot-password', [Admin\AuthController::class, 'sendReset'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [Admin\AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('reset-password', [Admin\AuthController::class, 'reset'])->name('password.update');
    Route::get('invite/{token}', [Admin\AuthController::class, 'inviteForm'])->name('invite');
    Route::post('invite/{token}', [Admin\AuthController::class, 'acceptInvite'])->name('invite.accept');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('two-factor', [Admin\AuthController::class, 'twoFactorForm'])->name('2fa.challenge');
    Route::post('two-factor', [Admin\AuthController::class, 'twoFactorVerify'])->middleware('throttle:10,1')->name('2fa.verify');
});

// ---------- Signed-in staff ----------
Route::prefix('admin')->name('admin.')->middleware(['auth:admin', 'admin.active', 'admin.2fa', 'admin.timeout'])->group(function () {
    Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');

    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('search', [Admin\SearchController::class, 'index'])->name('search');

    // Profile & notifications
    Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile');
    Route::put('profile', [Admin\ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [Admin\ProfileController::class, 'password'])->name('profile.password');
    Route::post('profile/2fa', [Admin\ProfileController::class, 'twoFactorStart'])->name('profile.2fa.start');
    Route::post('profile/2fa/confirm', [Admin\ProfileController::class, 'twoFactorConfirm'])->name('profile.2fa.confirm');
    Route::delete('profile/2fa', [Admin\ProfileController::class, 'twoFactorDisable'])->name('profile.2fa.disable');
    Route::post('profile/2fa/codes', [Admin\ProfileController::class, 'regenerateCodes'])->name('profile.2fa.codes');
    Route::post('profile/logout-others', [Admin\ProfileController::class, 'logoutOthers'])->name('profile.logout-others');
    Route::get('notifications', [Admin\ProfileController::class, 'notifications'])->name('notifications');
    Route::post('notifications/read', [Admin\ProfileController::class, 'readAll'])->name('notifications.read');
    Route::get('notifications/{notification}', [Admin\ProfileController::class, 'openNotification'])->name('notifications.open');

    // Stories
    Route::middleware('permission:content.view')->group(function () {
        Route::get('stories', [Admin\StoryController::class, 'index'])->name('stories.index');
        Route::get('stories/new', [Admin\StoryController::class, 'create'])->name('stories.create');
        Route::post('stories', [Admin\StoryController::class, 'store'])->name('stories.store');
        Route::get('stories/{story:id}/edit', [Admin\StoryController::class, 'edit'])->name('stories.edit');
        Route::put('stories/{story:id}', [Admin\StoryController::class, 'update'])->name('stories.update');
        Route::get('stories/{story:id}/preview', [Admin\StoryController::class, 'preview'])->name('stories.preview');
        Route::get('stories/{story:id}/history', [Admin\StoryController::class, 'history'])->name('stories.history');
        Route::post('stories/{story:id}/versions/{snapshot}/restore', [Admin\StoryController::class, 'restoreVersion'])->name('stories.version.restore');
        Route::post('stories/{story:id}/submit', [Admin\StoryController::class, 'submit'])->name('stories.submit');
        Route::post('stories/{story:id}/publish', [Admin\StoryController::class, 'publish'])->name('stories.publish');
        Route::post('stories/{story:id}/schedule', [Admin\StoryController::class, 'schedule'])->name('stories.schedule');
        Route::post('stories/{story:id}/cancel-schedule', [Admin\StoryController::class, 'cancelSchedule'])->name('stories.schedule.cancel');
        Route::post('stories/{story:id}/unpublish', [Admin\StoryController::class, 'unpublish'])->name('stories.unpublish');
        Route::post('stories/{story:id}/reopen', [Admin\StoryController::class, 'reopen'])->name('stories.reopen');
        Route::post('stories-bulk', [Admin\StoryController::class, 'bulk'])->name('stories.bulk');
        Route::get('stories-lookup', [Admin\StoryController::class, 'lookup'])->name('stories.lookup');
        Route::post('stories/{story:id}/translate', [Admin\TranslationController::class, 'request'])->middleware('permission:translation.request')->name('stories.translate');
    });
    Route::delete('stories/{story:id}', [Admin\StoryController::class, 'destroy'])->middleware(['permission:content.delete', 'password.confirmed.inline'])->name('stories.destroy');

    // Approval queue & schedule
    Route::middleware('permission:review.view')->group(function () {
        Route::get('review', [Admin\ReviewController::class, 'index'])->name('review.index');
        Route::get('review/{story:id}', [Admin\ReviewController::class, 'show'])->name('review.show');
        Route::post('review/{story:id}/take', [Admin\ReviewController::class, 'take'])->name('review.take');
    });
    Route::post('review/{story:id}/decide', [Admin\ReviewController::class, 'decide'])->middleware('permission:review.decide')->name('review.decide');
    Route::post('review/{story:id}/assign', [Admin\ReviewController::class, 'assign'])->middleware('permission:review.assign')->name('review.assign');
    Route::post('review/{story:id}/override', [Admin\ReviewController::class, 'override'])->middleware(['permission:review.override', 'password.confirmed.inline'])->name('review.override');
    Route::middleware('permission:content.view')->group(function () {
        Route::get('schedule', [Admin\ScheduleController::class, 'index'])->name('schedule.index');
        Route::get('schedule/events', [Admin\ScheduleController::class, 'events'])->name('schedule.events');
    });
    Route::post('schedule/{story:id}/move', [Admin\ScheduleController::class, 'move'])->middleware('permission:content.schedule')->name('schedule.move');

    // Newsroom automation
    Route::middleware('permission:feeds.manage')->group(function () {
        Route::get('newsroom/feeds', [Admin\NewsroomController::class, 'feeds'])->name('newsroom.feeds');
        Route::post('newsroom/feeds', [Admin\NewsroomController::class, 'feedStore'])->name('newsroom.feeds.store');
        Route::put('newsroom/feeds/{feed}', [Admin\NewsroomController::class, 'feedUpdate'])->name('newsroom.feeds.update');
        Route::post('newsroom/feeds/{feed}/toggle', [Admin\NewsroomController::class, 'feedToggle'])->name('newsroom.feeds.toggle');
        Route::post('newsroom/feeds/{feed}/fetch', [Admin\NewsroomController::class, 'feedFetch'])->name('newsroom.feeds.fetch');
        Route::delete('newsroom/feeds/{feed}', [Admin\NewsroomController::class, 'feedDestroy'])->name('newsroom.feeds.destroy');
        Route::post('newsroom/sources', [Admin\NewsroomController::class, 'sourceStore'])->name('newsroom.sources.store');
    });
    Route::get('newsroom/auto-publish', [Admin\NewsroomController::class, 'rules'])->middleware('permission:autopublish.manage')->name('newsroom.autopublish');
    Route::put('newsroom/auto-publish', [Admin\NewsroomController::class, 'rulesSave'])->middleware('permission:autopublish.manage')->name('newsroom.autopublish.save');
    Route::get('newsroom/log', [Admin\NewsroomController::class, 'log'])->middleware('permission:review.view|feeds.manage|autopublish.manage')->name('newsroom.log');
    Route::post('newsroom/log/{check}/unpublish', [Admin\NewsroomController::class, 'logUnpublish'])->middleware('permission:content.publish')->name('newsroom.log.unpublish');

    // Daily: Morning 5 and quiz
    Route::middleware('permission:briefing.manage')->group(function () {
        Route::get('daily/briefings', [Admin\DailyController::class, 'briefings'])->name('daily.briefings');
        Route::get('daily/briefings/new', [Admin\DailyController::class, 'briefingEdit'])->name('daily.briefings.create');
        Route::post('daily/briefings', [Admin\DailyController::class, 'briefingSave'])->name('daily.briefings.store');
        Route::post('daily/briefings/pick', [Admin\DailyController::class, 'briefingPick'])->name('daily.briefings.pick');
        Route::get('daily/briefings/{briefing}', [Admin\DailyController::class, 'briefingEdit'])->name('daily.briefings.edit');
        Route::put('daily/briefings/{briefing}', [Admin\DailyController::class, 'briefingSave'])->name('daily.briefings.update');
        Route::post('daily/briefings/{briefing}/audio', [Admin\DailyController::class, 'briefingAudio'])->name('daily.briefings.audio');
    });
    Route::middleware('permission:quiz.manage')->group(function () {
        Route::get('daily/quizzes', [Admin\DailyController::class, 'quizzes'])->name('daily.quizzes');
        Route::get('daily/quizzes/new', [Admin\DailyController::class, 'quizEdit'])->name('daily.quizzes.create');
        Route::post('daily/quizzes', [Admin\DailyController::class, 'quizSave'])->name('daily.quizzes.store');
        Route::post('daily/quizzes/draft', [Admin\DailyController::class, 'quizDraft'])->name('daily.quizzes.draft');
        Route::get('daily/quizzes/{quiz}', [Admin\DailyController::class, 'quizEdit'])->name('daily.quizzes.edit');
        Route::put('daily/quizzes/{quiz}', [Admin\DailyController::class, 'quizSave'])->name('daily.quizzes.update');
        Route::post('daily/quizzes/{quiz}/approve', [Admin\DailyController::class, 'quizApprove'])->name('daily.quizzes.approve');
        Route::delete('daily/quizzes/{quiz}', [Admin\DailyController::class, 'quizDestroy'])->name('daily.quizzes.destroy');
    });

    // Media
    Route::middleware('permission:media.upload')->group(function () {
        Route::get('media', [Admin\MediaController::class, 'index'])->name('media.index');
        Route::get('media/picker', [Admin\MediaController::class, 'picker'])->name('media.picker');
        Route::post('media', [Admin\MediaController::class, 'store'])->name('media.store');
        Route::get('media/{media}', [Admin\MediaController::class, 'show'])->name('media.show');
        Route::put('media/{media}', [Admin\MediaController::class, 'update'])->name('media.update');
    });
    Route::delete('media/{media}', [Admin\MediaController::class, 'destroy'])->middleware('permission:media.delete')->name('media.destroy');

    // Site: categories, tags, pages
    Route::middleware('permission:categories.view')->group(function () {
        Route::get('categories', [Admin\CategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/{category:id}', [Admin\CategoryController::class, 'show'])->name('categories.show');
    });
    Route::middleware('permission:categories.manage')->group(function () {
        Route::post('categories', [Admin\CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category:id}', [Admin\CategoryController::class, 'update'])->name('categories.update');
        Route::post('categories/{category:id}/toggle', [Admin\CategoryController::class, 'toggle'])->name('categories.toggle');
        Route::post('categories-reorder', [Admin\CategoryController::class, 'reorder'])->name('categories.reorder');
        Route::get('tags', [Admin\TagController::class, 'index'])->name('tags.index');
        Route::put('tags/{tag}', [Admin\TagController::class, 'update'])->name('tags.update');
        Route::delete('tags/{tag}', [Admin\TagController::class, 'destroy'])->name('tags.destroy');
    });
    Route::delete('categories/{category:id}', [Admin\CategoryController::class, 'destroy'])->middleware(['permission:categories.delete', 'password.confirmed.inline'])->name('categories.destroy');
    Route::middleware('permission:pages.manage')->group(function () {
        Route::get('pages', [Admin\PageController::class, 'index'])->name('pages.index');
        Route::get('pages/new', [Admin\PageController::class, 'create'])->name('pages.create');
        Route::post('pages', [Admin\PageController::class, 'store'])->name('pages.store');
        Route::get('pages/{page:id}', [Admin\PageController::class, 'edit'])->name('pages.edit');
        Route::put('pages/{page:id}', [Admin\PageController::class, 'update'])->name('pages.update');
        Route::delete('pages/{page:id}', [Admin\PageController::class, 'destroy'])->name('pages.destroy');
    });

    // Polls
    Route::middleware('permission:polls.view')->group(function () {
        Route::get('polls', [Admin\PollController::class, 'index'])->name('polls.index');
        Route::get('polls/{poll}', [Admin\PollController::class, 'show'])->name('polls.show')->whereNumber('poll');
        Route::get('polls/{poll}/export', [Admin\PollController::class, 'export'])->name('polls.export');
    });
    Route::middleware('permission:polls.manage')->group(function () {
        Route::get('polls/create', [Admin\PollController::class, 'create'])->name('polls.create');
        Route::post('polls', [Admin\PollController::class, 'store'])->name('polls.store');
        Route::get('polls/{poll}/edit', [Admin\PollController::class, 'edit'])->name('polls.edit');
        Route::put('polls/{poll}', [Admin\PollController::class, 'update'])->name('polls.update');
        Route::post('polls/{poll}/approve', [Admin\PollController::class, 'approve'])->name('polls.approve');
        Route::post('polls/{poll}/reject', [Admin\PollController::class, 'reject'])->name('polls.reject');
        Route::post('polls/{poll}/duplicate', [Admin\PollController::class, 'duplicate'])->name('polls.duplicate');
        Route::delete('polls/{poll}', [Admin\PollController::class, 'destroy'])->name('polls.destroy');
    });
    Route::post('polls/{poll}/close', [Admin\PollController::class, 'close'])->middleware('permission:polls.close')->name('polls.close');

    // Translations (English ↔ Hindi)
    Route::get('translations', [Admin\TranslationController::class, 'index'])->middleware('permission:translation.review|translation.request')->name('translations.index');
    Route::post('translations/backfill', [Admin\TranslationController::class, 'backfill'])->middleware('permission:translation.request')->name('translations.backfill');
    Route::middleware('permission:translation.review')->group(function () {
        Route::get('translations/{job}', [Admin\TranslationController::class, 'review'])->name('translations.review');
        Route::put('translations/{job}', [Admin\TranslationController::class, 'save'])->name('translations.save');
        Route::post('translations/{job}/approve', [Admin\TranslationController::class, 'approve'])->name('translations.approve');
        Route::post('translations/{job}/field', [Admin\TranslationController::class, 'retranslateField'])->name('translations.field');
        Route::post('translations/{job}/retry', [Admin\TranslationController::class, 'retry'])->name('translations.retry');
        Route::post('translations/{job}/reject', [Admin\TranslationController::class, 'reject'])->name('translations.reject');
    });
    Route::middleware('permission:translation.settings')->group(function () {
        Route::get('translation-settings', [Admin\TranslationController::class, 'settings'])->name('translations.settings');
        Route::put('translation-settings', [Admin\TranslationController::class, 'saveSettings'])->name('translations.settings.save');
        Route::get('glossary', [Admin\TranslationController::class, 'glossary'])->name('glossary.index');
        Route::post('glossary', [Admin\TranslationController::class, 'glossaryStore'])->name('glossary.store');
        Route::delete('glossary/{term}', [Admin\TranslationController::class, 'glossaryDestroy'])->name('glossary.destroy');
    });

    // Readers, comments, reports, streaks
    Route::middleware('permission:users.view')->group(function () {
        Route::get('readers', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('readers/{user}', [Admin\UserController::class, 'show'])->name('users.show');
    });
    Route::post('readers/{user}/status', [Admin\UserController::class, 'status'])->middleware('permission:users.restrict|users.suspend')->name('users.status');
    Route::post('readers/{user}/recalculate-streak', [Admin\UserController::class, 'recalcStreak'])->middleware('permission:streaks.manage')->name('users.streak.recalc');
    Route::post('readers/{user}/freeze', [Admin\UserController::class, 'grantFreeze'])->middleware('permission:streaks.manage')->name('users.streak.freeze');
    Route::get('readers/{user}/export', [Admin\UserController::class, 'export'])->middleware('role:Super Admin')->name('users.export');
    Route::post('readers/{user}/anonymise', [Admin\UserController::class, 'anonymise'])->middleware(['role:Super Admin', 'password.confirmed.inline'])->name('users.anonymise');
    Route::middleware('permission:comments.moderate')->group(function () {
        Route::get('comments', [Admin\CommentController::class, 'index'])->name('comments.index');
        Route::post('comments/{comment}/hide', [Admin\CommentController::class, 'hide'])->name('comments.hide');
        Route::post('comments/{comment}/restore', [Admin\CommentController::class, 'restore'])->name('comments.restore');
    });
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/log', [Admin\ReportController::class, 'log'])->name('reports.log');
        Route::get('reports/{report}', [Admin\ReportController::class, 'show'])->name('reports.show');
    });
    Route::post('reports/{report}/act', [Admin\ReportController::class, 'act'])->middleware('permission:reports.handle')->name('reports.act');
    Route::get('streaks', [Admin\StreakController::class, 'index'])->middleware('permission:streaks.manage|analytics.view')->name('streaks.index');
    Route::put('streaks', [Admin\StreakController::class, 'save'])->middleware(['permission:streaks.manage', 'password.confirmed.inline'])->name('streaks.save');

    // Analytics
    Route::get('analytics/{tab?}', [Admin\AnalyticsController::class, 'index'])->middleware('permission:analytics.view')->whereIn('tab', array_keys(Admin\AnalyticsController::TABS))->name('analytics');
    Route::post('analytics/{tab}/export', [Admin\AnalyticsController::class, 'export'])->middleware('permission:analytics.export')->name('analytics.export');

    // Administration
    Route::middleware('permission:admins.manage')->group(function () {
        Route::get('team', [Admin\AdminTeamController::class, 'index'])->name('team.index');
        Route::post('team', [Admin\AdminTeamController::class, 'invite'])->middleware('password.confirmed.inline')->name('team.invite');
        Route::get('team/{admin}', [Admin\AdminTeamController::class, 'edit'])->name('team.edit');
        Route::put('team/{admin}', [Admin\AdminTeamController::class, 'update'])->middleware('password.confirmed.inline')->name('team.update');
        Route::post('team/{admin}/toggle', [Admin\AdminTeamController::class, 'toggle'])->middleware('password.confirmed.inline')->name('team.toggle');
        Route::post('team/{admin}/reset-2fa', [Admin\AdminTeamController::class, 'resetTwoFactor'])->middleware('password.confirmed.inline')->name('team.reset2fa');
        Route::post('team/{admin}/resend', [Admin\AdminTeamController::class, 'resendInvite'])->name('team.resend');
    });
    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('roles', [Admin\RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [Admin\RoleController::class, 'store'])->middleware('password.confirmed.inline')->name('roles.store');
        Route::get('roles/{role}', [Admin\RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [Admin\RoleController::class, 'update'])->middleware('password.confirmed.inline')->name('roles.update');
        Route::delete('roles/{role}', [Admin\RoleController::class, 'destroy'])->middleware('password.confirmed.inline')->name('roles.destroy');
    });
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings/{tab?}', [Admin\SettingsController::class, 'show'])->name('settings');
        Route::put('settings/{tab}', [Admin\SettingsController::class, 'update'])->middleware('password.confirmed.inline')->name('settings.update');
    });
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('audit', [Admin\AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit/export', [Admin\AuditLogController::class, 'export'])->name('audit.export');
        Route::get('audit/{log}', [Admin\AuditLogController::class, 'show'])->name('audit.show');
    });
});
