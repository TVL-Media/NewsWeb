<?php

/*
| /api/v1 for the Android app. Readers authenticate with a Sanctum token (Authorization: Bearer …);
| reading works without one. JSON shapes match the website (App\Http\Resources\ReelResource).
*/

use App\Http\Controllers\Api;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ReelController;
use App\Http\Middleware\ApiReader;
use App\Http\Middleware\EnsureReelsEnabled;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware([ApiReader::class, 'throttle:120,1'])->group(function () {
    // Reels (REELS_ENABLED=false → 404)
    Route::middleware(EnsureReelsEnabled::class)->group(function () {
        Route::get('reels', [Api\ReelController::class, 'index'])->name('reels.index');
        Route::get('reels/{reel:id}', [Api\ReelController::class, 'show'])->whereNumber('reel')->name('reels.show');
        Route::get('reels/{reel:id}/comments', [CommentController::class, 'indexReel'])->whereNumber('reel')->name('reels.comments.index');
        Route::post('reels/{reel:id}/view', [ReelController::class, 'view'])->whereNumber('reel')->name('reels.view');
        Route::post('reels/{reel:id}/share', [ReelController::class, 'share'])->whereNumber('reel')->middleware('throttle:engage')->name('reels.share');

        Route::middleware(['auth:sanctum', 'throttle:engage'])->group(function () {
            Route::match(['post', 'delete'], 'reels/{reel:id}/like', [ReelController::class, 'like'])->whereNumber('reel')->name('reels.like');
            Route::match(['post', 'delete'], 'reels/{reel:id}/save', [ReelController::class, 'save'])->whereNumber('reel')->name('reels.save');
            Route::post('reels/{reel:id}/comments', [CommentController::class, 'storeReel'])->whereNumber('reel')->middleware('throttle:comments')->name('reels.comments.store');
            Route::post('reels/{reel:id}/report', [Api\ReelController::class, 'report'])->whereNumber('reel')->name('reels.report');
        });
    });
});
