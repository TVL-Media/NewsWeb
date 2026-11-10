<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\BriefingController;
use App\Http\Controllers\PollController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\EngagementController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\ForYouController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StoryController;
use Illuminate\Support\Facades\Route;

// ── Reading (guests welcome) ──────────────────────────────────────────────
Route::get('/', HomeController::class)->name('home');
Route::get('/explore', ExploreController::class)->name('explore');
Route::get('/search', SearchController::class)->middleware('throttle:60,1')->name('search');
Route::get('/story/{story:slug}', [StoryController::class, 'show'])->name('story.show');
Route::get('/page/{page:slug}', [PageController::class, 'show'])->name('page.show');
Route::get('/stories/{story:id}/comments', [CommentController::class, 'index'])->name('comments.index');

Route::post('/preferences/language', LanguageController::class)->middleware('throttle:30,1')->name('preferences.language');

// ── Static pages ──────────────────────────────────────────────────────────
Route::view('/about', 'static.about')->name('about');
Route::view('/grievance-officer', 'static.grievance')->name('grievance');
Route::view('/privacy', 'static.privacy')->name('privacy');
Route::view('/terms', 'static.terms')->name('terms');
Route::view('/community-rules', 'static.community')->name('community');

// ── Login (phone + OTP) ───────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::post('/auth/otp/send', [OtpController::class, 'send'])->middleware('throttle:otp')->name('otp.send');
    Route::post('/auth/otp/verify', [OtpController::class, 'verify'])->middleware('throttle:otp-verify')->name('otp.verify');
});
Route::post('/logout', [OtpController::class, 'logout'])->middleware('auth')->name('logout');

// ── Signed-in readers ─────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::get('/for-you', ForYouController::class)->name('for-you');

    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
    Route::get('/account/{type}', [AccountController::class, 'library'])->whereIn('type', AccountController::LIBRARY)->name('account.library');

    Route::middleware('throttle:engage')->group(function () {
        Route::match(['post', 'delete'], '/stories/{story:id}/like', [EngagementController::class, 'like'])->name('stories.like');
        Route::put('/stories/{story:id}/reaction', [EngagementController::class, 'reaction'])->name('stories.reaction');
        Route::match(['post', 'delete'], '/stories/{story:id}/save', [EngagementController::class, 'save'])->name('stories.save');
        Route::post('/stories/{story:id}/share', [EngagementController::class, 'share'])->name('stories.share');
        Route::post('/stories/{story:id}/read', [EngagementController::class, 'read'])->name('stories.read');
        Route::post('/stories/{story:id}/comments', [CommentController::class, 'store'])->middleware('throttle:comments')->name('comments.store');
        Route::match(['post', 'delete'], '/comments/{comment}/like', [EngagementController::class, 'likeComment'])->name('comments.like');
        Route::match(['post', 'delete'], '/pages/{page:slug}/follow', [EngagementController::class, 'follow'])->name('pages.follow');
        Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
    });

    // Habit features
    Route::get('/quiz', [QuizController::class, 'show'])->name('quiz');
    Route::post('/quiz/answers', [QuizController::class, 'answer'])->middleware('throttle:60,1')->name('quiz.answer');
    Route::post('/quiz/share', [QuizController::class, 'share'])->middleware('throttle:10,1')->name('quiz.share');
    Route::post('/polls/{poll}/vote', [PollController::class, 'vote'])->middleware('throttle:engage')->name('polls.vote');
    Route::post('/polls', [PollController::class, 'store'])->middleware('throttle:10,1')->name('polls.store');
});

Route::get('/briefing', BriefingController::class)->name('briefing');




// ── Newsroom admin panel (/admin) ─────────────────────────────────────────
require __DIR__.'/admin.php';
