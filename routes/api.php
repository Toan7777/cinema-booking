<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\MovieController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

// ===== AUTH (public) =====
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// ===== AUTH (cần đăng nhập) =====
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/bookings/lock-seats', [BookingController::class, 'lockSeats']);
    Route::delete('/bookings/release-seats', [BookingController::class, 'releaseSeats']);
    Route::post('/bookings/confirm', [BookingController::class, 'confirmBooking']);
});

// ===== MOVIES =====
Route::get('/movies', [MovieController::class, 'index']);
Route::get('/movies/{id}', [MovieController::class, 'show']);

Route::middleware(['auth:sanctum', 'role:ADMIN'])->group(function () {
    Route::post('/movies', [MovieController::class, 'store']);
    Route::patch('/movies/{id}', [MovieController::class, 'update']);
    Route::delete('/movies/{id}', [MovieController::class, 'destroy']);
});

// ===== Sơ đồ ghế =====
Route::get('/showtimes/{id}/seats', [BookingController::class, 'getSeatMap']);

// ===== Route API lấy suất chiếu theo phim =====
Route::get('/movies/{id}/showtimes', function ($id) {
    $showtimes = DB::table('showtimes')
        ->where('movie_id', $id)
        ->get();
    return response()->json($showtimes);
});

// ===== TẠM: dọn DB + seed phim mới - XÓA SAU KHI DÙNG =====
Route::get('/maintenance/reset-and-seed/{secret}', function ($secret) {
    if ($secret !== 'reset2026xyz') abort(403);

    // Dọn dữ liệu cũ (giữ lại id=1, xóa trùng)
    DB::table('booking_seats')->where('showtime_id', '>', 1)->delete();
    DB::table('showtimes')->where('id', '>', 1)->delete();
    DB::table('seats')->where('room_id', '>', 1)->delete();
    DB::table('rooms')->where('id', '>', 1)->delete();
    DB::table('cinemas')->where('id', '>', 1)->delete();
    DB::table('movies')->where('id', '>', 1)->delete();

    // Sửa phim id=1 thành tên hợp lệ
    DB::table('movies')->where('id', 1)->update([
        'title' => 'Hành Trình Vô Tận',
        'duration_minutes' => 130,
        'genre' => 'Khoa học viễn tưởng',
        'description' => 'Một chuyến hành trình xuyên không gian đầy hiểm nguy.',
        'poster_url' => 'https://images.unsplash.com/photo-1464802686167-b939a6910659?w=400&q=80',
    ]);

    // Thêm 3 phim mới
    $movie2 = DB::table('movies')->insertGetId([
        'title' => 'Mật Mã Đêm Tối',
        'duration_minutes' => 115,
        'genre' => 'Hành động / Bí ẩn',
        'description' => 'Thám tử lừng danh truy tìm bí mật ẩn giấu trong bóng đêm.',
        'poster_url' => 'https://images.unsplash.com/photo-1509347528160-9a9e33742cdb?w=400&q=80',
    ]);

    $movie3 = DB::table('movies')->insertGetId([
        'title' => 'Giấc Mơ Hollywood',
        'duration_minutes' => 105,
        'genre' => 'Tâm lý / Lãng mạn',
        'description' => 'Câu chuyện tình yêu và ước mơ giữa kinh đô điện ảnh.',
        'poster_url' => 'https://images.unsplash.com/photo-1519608425089-7f3bfa6f6bb8?w=400&q=80',
    ]);

    $movie4 = DB::table('movies')->insertGetId([
        'title' => 'Thành Phố Không Ngủ',
        'duration_minutes' => 120,
        'genre' => 'Hành động / Tội phạm',
        'description' => 'Cuộc chiến sinh tử trong lòng thành phố không bao giờ ngủ.',
        'poster_url' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=400&q=80',
    ]);

    // Lấy room_id=1 và cinema_id=1 đã có
    // Tạo suất chiếu cho 3 phim mới (dùng cùng phòng 1)
    $showtime2 = DB::table('showtimes')->insertGetId([
        'movie_id' => $movie2,
        'room_id' => 1,
        'start_time' => '2026-07-10 14:00:00',
        'end_time' => '2026-07-10 15:55:00',
        'base_price' => 80000,
    ]);

    $showtime3 = DB::table('showtimes')->insertGetId([
        'movie_id' => $movie3,
        'room_id' => 1,
        'start_time' => '2026-07-10 16:30:00',
        'end_time' => '2026-07-10 18:15:00',
        'base_price' => 75000,
    ]);

    $showtime4 = DB::table('showtimes')->insertGetId([
        'movie_id' => $movie4,
        'room_id' => 1,
        'start_time' => '2026-07-10 20:00:00',
        'end_time' => '2026-07-10 22:00:00',
        'base_price' => 85000,
    ]);

    // Seed booking_seats cho 3 suất chiếu mới
    $seatIds = DB::table('seats')->where('room_id', 1)->pluck('id');
    foreach ([$showtime2, $showtime3, $showtime4] as $showtimeId) {
        $rows = $seatIds->map(fn($seatId) => [
            'showtime_id' => $showtimeId,
            'seat_id' => $seatId,
            'status' => 'AVAILABLE',
        ])->toArray();
        DB::table('booking_seats')->insert($rows);
    }

    return response()->json([
        'message' => 'Dọn + seed thành công!',
        'movies' => DB::table('movies')->get(),
        'showtimes' => DB::table('showtimes')->get(),
    ]);
});
