<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\MovieController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

// ===== AUTH =====
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

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

// ===== PUBLIC APIs =====
Route::get('/showtimes/{id}/seats', [BookingController::class, 'getSeatMap']);
Route::get('/movies/{id}/showtimes', function ($id) {
    $showtimes = DB::table('showtimes')
        ->join('rooms', 'rooms.id', '=', 'showtimes.room_id')
        ->join('cinemas', 'cinemas.id', '=', 'rooms.cinema_id')
        ->where('showtimes.movie_id', $id)
        ->select('showtimes.*', 'cinemas.name as cinema_name', 'cinemas.id as cinema_id', 'rooms.name as room_name')
        ->get();
    return response()->json($showtimes);
});

// ===== API lấy danh sách rạp =====
Route::get('/cinemas', function () {
    return response()->json(DB::table('cinemas')->get());
});

// ===== API movies với filter =====
Route::get('/movies-filter', function () {
    $genre = request('genre');
    $status = request('status'); // dang-chieu, sap-chieu
    $cinema = request('cinema');

    $query = DB::table('movies');

    if ($genre) {
        $query->where('genre', 'like', "%$genre%");
    }

    if ($status === 'sap-chieu') {
        $query->where('release_date', '>', now());
    } elseif ($status === 'dang-chieu') {
        $query->where(function ($q) {
            $q->whereNull('release_date')->orWhere('release_date', '<=', now());
        });
    }

    return response()->json($query->get());
});

/**
 * Helper dùng chung: tạo ghế hàng loạt cho 1 phòng (bulk insert thay vì insert từng dòng)
 * Bọc trong function_exists() vì file routes có thể được load nhiều lần trong 1 tiến trình
 * (ví dụ lúc artisan chạy route:cache / config:cache khi build) gây lỗi "Cannot redeclare".
 */
if (!function_exists('seedSeatsForRoom')) {
    function seedSeatsForRoom(int $roomId): void
    {
        $rowTypes = ['A' => 'NORMAL', 'B' => 'NORMAL', 'C' => 'VIP', 'D' => 'VIP', 'E' => 'COUPLE'];
        $rows = [];
        foreach ($rowTypes as $row => $type) {
            for ($col = 1; $col <= 8; $col++) {
                $rows[] = [
                    'room_id' => $roomId,
                    'row_label' => $row,
                    'col_number' => $col,
                    'seat_type' => $type,
                    'is_active' => true,
                ];
            }
        }
        DB::table('seats')->insert($rows);
    }
}

/**
 * Helper dùng chung: tạo booking_seats hàng loạt cho 1 showtime dựa trên các ghế của phòng
 */
if (!function_exists('seedBookingSeatsForShowtime')) {
    function seedBookingSeatsForShowtime(int $showtimeId, int $roomId): void
    {
        $seatIds = DB::table('seats')->where('room_id', $roomId)->pluck('id');
        if ($seatIds->isEmpty()) {
            return;
        }
        $rows = $seatIds->map(fn ($seatId) => [
            'showtime_id' => $showtimeId,
            'seat_id' => $seatId,
            'status' => 'AVAILABLE',
        ])->toArray();
        DB::table('booking_seats')->insert($rows);
    }
}

// ===== TẠM: Thêm rạp + phim mới =====
Route::get('/maintenance/add-cinemas/{secret}', function ($secret) {
    if ($secret !== 'addcinema2026') abort(403);

    return DB::transaction(function () {
        // Thêm 2 rạp mới
        $cinema2 = DB::table('cinemas')->insertGetId([
            'name' => 'CGV Aeon Mall Long Biên',
            'address' => 'Số 27 Cổ Linh, Long Biên, Hà Nội',
        ]);
        $cinema3 = DB::table('cinemas')->insertGetId([
            'name' => 'Lotte Cinema Landmark 72',
            'address' => 'Keangnam Landmark72, Phạm Hùng, Hà Nội',
        ]);

        // Thêm phòng cho 2 rạp mới
        $room2 = DB::table('rooms')->insertGetId(['cinema_id' => $cinema2, 'name' => 'Phòng 1', 'rows_count' => 5, 'cols_count' => 8]);
        $room3 = DB::table('rooms')->insertGetId(['cinema_id' => $cinema3, 'name' => 'Phòng 1', 'rows_count' => 5, 'cols_count' => 8]);

        // Thêm ghế cho 2 phòng mới (bulk insert)
        seedSeatsForRoom($room2);
        seedSeatsForRoom($room3);

        // Thêm 3 phim mới (sắp chiếu)
        $movie5 = DB::table('movies')->insertGetId([
            'title' => 'Chiến Binh Bóng Đêm',
            'duration_minutes' => 125,
            'genre' => 'Hành động',
            'description' => 'Cuộc chiến sinh tử giữa ánh sáng và bóng tối.',
            'poster_url' => 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=400&q=80',
        ]);
        $movie6 = DB::table('movies')->insertGetId([
            'title' => 'Tình Yêu Mùa Hạ',
            'duration_minutes' => 100,
            'genre' => 'Tâm lý / Lãng mạn',
            'description' => 'Câu chuyện tình yêu ngọt ngào dưới nắng hè.',
            'poster_url' => 'https://images.unsplash.com/photo-1522869635100-9f4c5e86aa37?w=400&q=80',
        ]);
        $movie7 = DB::table('movies')->insertGetId([
            'title' => 'Vũ Trụ Song Song',
            'duration_minutes' => 140,
            'genre' => 'Khoa học viễn tưởng',
            'description' => 'Hành trình xuyên không gian và thời gian.',
            'poster_url' => 'https://images.unsplash.com/photo-1446776877081-d282a0f896e2?w=400&q=80',
        ]);

        // Thêm suất chiếu cho các rạp mới
        $showtimeData = [
            ['movie_id' => 1,       'room_id' => $room2, 'start_time' => '2026-07-10 10:00:00', 'end_time' => '2026-07-10 12:10:00', 'base_price' => 80000],
            ['movie_id' => $movie5, 'room_id' => $room2, 'start_time' => '2026-07-10 14:00:00', 'end_time' => '2026-07-10 16:05:00', 'base_price' => 85000],
            ['movie_id' => $movie6, 'room_id' => $room3, 'start_time' => '2026-07-10 15:00:00', 'end_time' => '2026-07-10 16:40:00', 'base_price' => 75000],
            ['movie_id' => $movie7, 'room_id' => $room3, 'start_time' => '2026-07-10 19:00:00', 'end_time' => '2026-07-10 21:20:00', 'base_price' => 90000],
        ];

        foreach ($showtimeData as $st) {
            $showtimeId = DB::table('showtimes')->insertGetId($st);
            seedBookingSeatsForShowtime($showtimeId, $st['room_id']);
        }

        return response()->json([
            'message' => 'Thêm rạp + phim thành công!',
            'cinemas' => DB::table('cinemas')->get(),
            'movies' => DB::table('movies')->get(),
        ]);
    });
});

// ===== TẠM: Dọn phim trùng + reset sạch =====
Route::get('/maintenance/clean-movies/{secret}', function ($secret) {
    if ($secret !== 'cleanmovie2026') abort(403);

    return DB::transaction(function () {
        // Xóa toàn bộ dữ liệu liên quan (trừ id 1-12 đã có từ trước)
        DB::table('booking_seats')->whereIn('showtime_id', function ($q) {
            $q->select('id')->from('showtimes')->where('id', '>', 12);
        })->delete();
        DB::table('showtimes')->where('id', '>', 12)->delete();
        DB::table('seats')->where('room_id', '>', 1)->delete();
        DB::table('rooms')->where('id', '>', 1)->delete();
        DB::table('cinemas')->where('id', '>', 1)->delete();
        DB::table('movies')->where('id', '>', 12)->delete();

        // Giờ thêm lại đúng 1 lần
        $cinema2 = DB::table('cinemas')->insertGetId([
            'name' => 'CGV Aeon Mall Long Biên',
            'address' => 'Số 27 Cổ Linh, Long Biên, Hà Nội',
        ]);
        $cinema3 = DB::table('cinemas')->insertGetId([
            'name' => 'Lotte Cinema Landmark 72',
            'address' => 'Keangnam Landmark72, Phạm Hùng, Hà Nội',
        ]);

        // Phòng
        $room2 = DB::table('rooms')->insertGetId(['cinema_id' => $cinema2, 'name' => 'Phòng 1', 'rows_count' => 5, 'cols_count' => 8]);
        $room3 = DB::table('rooms')->insertGetId(['cinema_id' => $cinema3, 'name' => 'Phòng 1', 'rows_count' => 5, 'cols_count' => 8]);

        // Ghế (bulk insert)
        seedSeatsForRoom($room2);
        seedSeatsForRoom($room3);

        // 3 phim mới
        $movie5 = DB::table('movies')->insertGetId([
            'title' => 'Chiến Binh Bóng Đêm',
            'duration_minutes' => 125,
            'genre' => 'Hành động',
            'description' => 'Cuộc chiến sinh tử giữa ánh sáng và bóng tối.',
            'poster_url' => 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=400&q=80',
        ]);
        $movie6 = DB::table('movies')->insertGetId([
            'title' => 'Tình Yêu Mùa Hạ',
            'duration_minutes' => 100,
            'genre' => 'Tâm lý / Lãng mạn',
            'description' => 'Câu chuyện tình yêu ngọt ngào dưới nắng hè.',
            'poster_url' => 'https://images.unsplash.com/photo-1522869635100-9f4c5e86aa37?w=400&q=80',
        ]);
        $movie7 = DB::table('movies')->insertGetId([
            'title' => 'Vũ Trụ Song Song',
            'duration_minutes' => 140,
            'genre' => 'Khoa học viễn tưởng',
            'description' => 'Hành trình xuyên không gian và thời gian.',
            'poster_url' => 'https://images.unsplash.com/photo-1446776877081-d282a0f896e2?w=400&q=80',
        ]);

        // Suất chiếu: phim cũ chiếu ở rạp mới + phim mới
        $showtimes = [
            // Phim cũ chiếu ở rạp 2 (CGV Long Biên)
            ['movie_id' => 1,       'room_id' => $room2, 'start_time' => '2026-07-12 10:00:00', 'end_time' => '2026-07-12 12:10:00', 'base_price' => 80000],
            ['movie_id' => 10,      'room_id' => $room2, 'start_time' => '2026-07-12 14:00:00', 'end_time' => '2026-07-12 15:55:00', 'base_price' => 85000],
            // Phim mới ở rạp 2
            ['movie_id' => $movie5, 'room_id' => $room2, 'start_time' => '2026-07-12 18:00:00', 'end_time' => '2026-07-12 20:05:00', 'base_price' => 85000],
            // Phim ở rạp 3 (Lotte Landmark)
            ['movie_id' => 1,       'room_id' => $room3, 'start_time' => '2026-07-12 11:00:00', 'end_time' => '2026-07-12 13:10:00', 'base_price' => 90000],
            ['movie_id' => $movie6, 'room_id' => $room3, 'start_time' => '2026-07-12 15:00:00', 'end_time' => '2026-07-12 16:40:00', 'base_price' => 75000],
            ['movie_id' => $movie7, 'room_id' => $room3, 'start_time' => '2026-07-12 19:00:00', 'end_time' => '2026-07-12 21:20:00', 'base_price' => 90000],
        ];

        foreach ($showtimes as $st) {
            $showtimeId = DB::table('showtimes')->insertGetId($st);
            seedBookingSeatsForShowtime($showtimeId, $st['room_id']);
        }

        return response()->json([
            'message' => 'Dọn + seed sạch thành công!',
            'cinemas' => DB::table('cinemas')->get(),
            'movies_count' => DB::table('movies')->count(),
            'showtimes_count' => DB::table('showtimes')->count(),
        ]);
    });
});
