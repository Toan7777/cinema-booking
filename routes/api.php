<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\MovieController;
use Illuminate\Support\Facades\Route;

// ===== AUTH (public) =====
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// ===== AUTH (cần đăng nhập - dùng Sanctum session cookie) =====
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Đặt vé - chỉ user đã đăng nhập mới giữ/đặt ghế được
    Route::post('/bookings/lock-seats', [BookingController::class, 'lockSeats']);
    Route::delete('/bookings/release-seats', [BookingController::class, 'releaseSeats']);
    Route::post('/bookings/confirm', [BookingController::class, 'confirmBooking']);
});

// ===== MOVIES (public đọc, ADMIN mới ghi) =====
Route::get('/movies', [MovieController::class, 'index']);
Route::get('/movies/{id}', [MovieController::class, 'show']);

Route::middleware(['auth:sanctum', 'role:ADMIN'])->group(function () {
    Route::post('/movies', [MovieController::class, 'store']);
    Route::patch('/movies/{id}', [MovieController::class, 'update']);
    Route::delete('/movies/{id}', [MovieController::class, 'destroy']);
});

// ===== Sơ đồ ghế (public - ai cũng xem được, không cần đăng nhập) =====
Route::get('/showtimes/{id}/seats', [BookingController::class, 'getSeatMap']);
