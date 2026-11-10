<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Contracts\TextToSpeech;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\Category;
use App\Models\Poll;
use App\Models\Report;
use App\Models\Story;
use App\Models\TranslationJob;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\Msg91Sender;
use App\Services\Tts\FakeTts;
use App\Services\Tts\GoogleTts;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TextToSpeech::class, fn () => config('services.tts.driver') === 'google'
            ? new GoogleTts(config('services.tts.google_key'))
            : new FakeTts);

        $this->app->bind(SmsSender::class, fn () => config('services.sms.driver') === 'msg91'
            ? new Msg91Sender(config('services.msg91.auth_key'), config('services.msg91.template_id'), config('services.msg91.sender_id'))
            : new LogSmsSender);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        // The admin panel's list screens favour simple code over eager loading everywhere,
        // so lazy-loading is only enforced on the reader-facing site.
        Model::handleLazyLoadingViolationUsing(function ($model, string $relation) {
            if (app()->runningInConsole() && ! app()->runningUnitTests() || request()->is('admin', 'admin/*')) {
                return;
            }
            throw new \Illuminate\Database\LazyLoadingViolationException($model, $relation);
        });

        // ── Admin panel ──
        // Super Admin can do everything.
        Gate::before(fn ($user) => $user instanceof Admin && $user->hasRole('Super Admin') ? true : null);
        Paginator::defaultView('admin.partials.pager');
        View::composer('admin.layouts.app', function ($view) {
            $a = auth('admin')->user();
            if (! $a) {
                return;
            }
            $view->with('navCounts', [
                'review' => $a->can('review.view') ? Story::where('status', 'pending_approval')->count() : 0,
                'reports' => $a->can('reports.view') ? Report::whereIn('status', ['open', 'escalated'])->distinct()->get(['target_type', 'target_id'])->count() : 0,
                'polls' => $a->can('polls.view') ? Poll::where('status', 'pending')->count() : 0,
                'translations' => $a->can('translation.review') ? TranslationJob::where('status', 'ai_completed')->count() : 0,
                'unread' => AdminNotification::where('admin_id', $a->id)->whereNull('read_at')->count(),
            ]);
            $view->with('navNotifications', AdminNotification::where('admin_id', $a->id)->latest('created_at')->limit(8)->get());
        });
        RateLimiter::for('admin-login', fn (Request $r) => [
            Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);

        // Rate limits: OTP by IP and phone; engagement and comments per reader.
        RateLimiter::for('otp', fn (Request $r) => [
            Limit::perMinute(5)->by('otp-ip:'.$r->ip()),
            Limit::perHour(10)->by('otp-phone:'.$r->input('phone')),
        ]);
        RateLimiter::for('otp-verify', fn (Request $r) => Limit::perMinute(10)->by('otpv:'.$r->ip()));
        RateLimiter::for('engage', fn (Request $r) => Limit::perMinute(90)->by('engage:'.$r->user()?->id));
        RateLimiter::for('comments', fn (Request $r) => Limit::perMinute(5)->by('comments:'.$r->user()?->id));

        // Category chips row (header). Cached for 10 minutes; cleared by the seeder and admin edits.
        // Plain arrays are cached (Laravel 13's cache won't unserialize objects by default).
        View::composer('components.category-chips', function ($view) {
            $rows = Cache::remember('nav.categories', 600,
                fn () => Category::active()->orderBy('sort_order')->get()->map->getAttributes()->all());
            $view->with('navCategories', Category::hydrate($rows));
        });
    }
}
