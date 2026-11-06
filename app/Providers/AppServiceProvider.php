<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Contracts\TextToSpeech;
use App\Models\Category;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\Msg91Sender;
use App\Services\Tts\FakeTts;
use App\Services\Tts\GoogleTts;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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
                fn () => Category::orderBy('sort_order')->get()->map->getAttributes()->all());
            $view->with('navCategories', Category::hydrate($rows));
        });
    }
}
