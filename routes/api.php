<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

// The check-in app. Sign in with e-mail and password (users with the check-in permission) to get a device token;
// every other call sends it as "Authorization: Bearer <token>".

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login');

    Route::middleware(['api.token:'.Permissions::CHECKIN, 'throttle:240,1'])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/events', [CheckInController::class, 'events'])->name('events');
        Route::get('/events/{event}/stats', [CheckInController::class, 'stats'])->name('events.stats');
        Route::get('/events/{event}/tickets', [CheckInController::class, 'tickets'])->name('events.tickets');
        Route::post('/events/{event}/checkin', [CheckInController::class, 'scan'])->name('events.checkin');
        Route::post('/events/{event}/checkin/undo', [CheckInController::class, 'undo'])->name('events.checkin.undo');
    });
});
