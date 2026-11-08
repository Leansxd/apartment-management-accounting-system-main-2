<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ResidentController;
use Illuminate\Support\Facades\Route;

// Mobil (sakin) uygulaması API'si. Kimlik doğrulama: Authorization: Bearer <token> (Sanctum).
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Davet koduyla daireye bağlanma (deneme sınırı sunucuda tutulur)
    Route::post('/redeem-invite', [ResidentController::class, 'redeem'])->middleware('throttle:invite');

    // Sakin yalnızca bağlı olduğu dairelerin kayıtlarını görür
    Route::prefix('sites/{apartment}')->middleware('resident')->group(function () {
        Route::get('/debts', [ResidentController::class, 'debts']);
        Route::get('/payments', [ResidentController::class, 'payments']);
        Route::get('/statement', [ResidentController::class, 'statement']);
        Route::get('/announcements', [ResidentController::class, 'announcements']);
        Route::get('/notifications', [ResidentController::class, 'notifications']);
        Route::post('/notifications/{notification}/read', [ResidentController::class, 'markRead']);
        Route::get('/polls', [ResidentController::class, 'polls']);
        Route::post('/polls/{poll}/vote', [ResidentController::class, 'vote']);
        Route::get('/areas', [ResidentController::class, 'areas']);
        Route::get('/reservations', [ResidentController::class, 'reservations']);
        Route::post('/reservations', [ResidentController::class, 'storeReservation']);
        Route::delete('/reservations/{reservation}', [ResidentController::class, 'cancelReservation']);
        Route::get('/meetings', [ResidentController::class, 'meetings']);
        Route::get('/tickets', [ResidentController::class, 'tickets']);
        Route::post('/tickets', [ResidentController::class, 'storeTicket']);
    });
});
