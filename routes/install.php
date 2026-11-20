<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

// Web installer. Only exists until the app is installed (installer.open → 404 afterwards).
Route::prefix('install')->name('install.')->middleware('installer.open')->group(function () {
    Route::get('/', [InstallController::class, 'index'])->name('index');
    Route::post('database/test', [InstallController::class, 'testDatabase'])->middleware('throttle:10,1')->name('database.test');
    Route::post('run/{task}', [InstallController::class, 'run'])->where('task', '[a-z]+')->name('run');
    Route::post('license', [InstallController::class, 'save'])->defaults('step', 'license')->middleware('throttle:5,1')->name('license');
    Route::get('{step}', [InstallController::class, 'show'])->where('step', '[a-z]+')->name('step');
    Route::post('{step}', [InstallController::class, 'save'])->where('step', '[a-z]+')->name('save');
});
