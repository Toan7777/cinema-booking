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

// ===== MOVIES (public đọc) =====
Route::get('/movies', [MovieController::class, 'index']);
Route::get('/movies/{id}', [MovieController::class, 'show']);

// ===== PUBLIC APIs =====
Route::get('/showtimes/{id}/seats', [BookingController::class, 'getSeatMap']);
Route::get('/cinemas', function () {
    return response()->json(DB::table('cinemas')->get());
});
Route::get('/movies/{id}/showtimes', function ($id) {
    $showtimes = DB::table('showtimes')
        ->join('rooms', 'rooms.id', '=', 'showtimes.room_id')
        ->join('cinemas', 'cinemas.id', '=', 'rooms.cinema_id')
        ->where('showtimes.movie_id', $id)
        ->select('showtimes.*', 'cinemas.name as cinema_name', 'cinemas.id as cinema_id', 'rooms.name as room_name')
        ->get();
    return response()->json($showtimes);
});

// ===== ADMIN ROUTES =====
Route::middleware(['auth:sanctum', 'role:ADMIN'])->prefix('admin')->group(function () {

    // --- MOVIES CRUD ---
    Route::get('/movies', [MovieController::class, 'index']);
    Route::post('/movies', [MovieController::class, 'store']);
    Route::patch('/movies/{id}', [MovieController::class, 'update']);
    Route::delete('/movies/{id}', [MovieController::class, 'destroy']);

    // --- CINEMAS CRUD ---
    Route::get('/cinemas', function () {
        return response()->json(DB::table('cinemas')->get());
    });
    Route::post('/cinemas', function (\Illuminate\Http\Request $req) {
        $data = $req->validate(['name' => 'required|string', 'address' => 'nullable|string']);
        $id = DB::table('cinemas')->insertGetId($data);
        return response()->json(DB::table('cinemas')->find($id), 201);
    });
    Route::patch('/cinemas/{id}', function (\Illuminate\Http\Request $req, $id) {
        $data = $req->validate(['name' => 'sometimes|string', 'address' => 'nullable|string']);
        DB::table('cinemas')->where('id', $id)->update($data);
        return response()->json(DB::table('cinemas')->find($id));
    });
    Route::delete('/cinemas/{id}', function ($id) {
        DB::table('cinemas')->where('id', $id)->delete();
        return response()->json(['message' => 'Đã xóa rạp']);
    });

    // --- ROOMS CRUD ---
    Route::get('/cinemas/{cinemaId}/rooms', function ($cinemaId) {
        return response()->json(DB::table('rooms')->where('cinema_id', $cinemaId)->get());
    });
    Route::post('/rooms', function (\Illuminate\Http\Request $req) {
        $data = $req->validate(['cinema_id' => 'required|integer', 'name' => 'required|string', 'rows_count' => 'required|integer', 'cols_count' => 'required|integer']);
        $roomId = DB::table('rooms')->insertGetId($data);
        // Tự động seed ghế
        $rows = ['A' => 'NORMAL', 'B' => 'NORMAL', 'C' => 'VIP', 'D' => 'VIP', 'E' => 'COUPLE'];
        foreach ($rows as $row => $type) {
            for ($col = 1; $col <= $data['cols_count']; $col++) {
                DB::table('seats')->insert(['room_id' => $roomId, 'row_label' => $row, 'col_number' => $col, 'seat_type' => $type, 'is_active' => true]);
            }
        }
        return response()->json(DB::table('rooms')->find($roomId), 201);
    });

    // --- SHOWTIMES CRUD ---
    Route::get('/showtimes', function () {
        return response()->json(
            DB::table('showtimes')
                ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
                ->join('rooms', 'rooms.id', '=', 'showtimes.room_id')
                ->join('cinemas', 'cinemas.id', '=', 'rooms.cinema_id')
                ->select('showtimes.*', 'movies.title as movie_title', 'rooms.name as room_name', 'cinemas.name as cinema_name')
                ->orderBy('showtimes.start_time', 'desc')
                ->get()
        );
    });
    Route::post('/showtimes', function (\Illuminate\Http\Request $req) {
        $data = $req->validate([
            'movie_id'   => 'required|integer',
            'room_id'    => 'required|integer',
            'start_time' => 'required|date',
            'end_time'   => 'required|date',
            'base_price' => 'required|numeric',
        ]);
        $showtimeId = DB::table('showtimes')->insertGetId($data);
        // Seed booking_seats tự động
        $seatIds = DB::table('seats')->where('room_id', $data['room_id'])->pluck('id');
        foreach ($seatIds as $seatId) {
            DB::table('booking_seats')->insert(['showtime_id' => $showtimeId, 'seat_id' => $seatId, 'status' => 'AVAILABLE']);
        }
        return response()->json(DB::table('showtimes')->find($showtimeId), 201);
    });
    Route::delete('/showtimes/{id}', function ($id) {
        DB::table('booking_seats')->where('showtime_id', $id)->delete();
        DB::table('showtimes')->where('id', $id)->delete();
        return response()->json(['message' => 'Đã xóa suất chiếu']);
    });

    // --- BOOKINGS & DOANH THU ---
    Route::get('/bookings', function () {
        return response()->json(
            DB::table('bookings')
                ->join('users', 'users.id', '=', 'bookings.user_id')
                ->join('showtimes', 'showtimes.id', '=', 'bookings.showtime_id')
                ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
                ->select(
                    'bookings.*',
                    'users.full_name as user_name',
                    'users.email as user_email',
                    'movies.title as movie_title',
                    'showtimes.start_time'
                )
                ->orderBy('bookings.created_at', 'desc')
                ->get()
        );
    });

    Route::get('/stats', function () {
        $totalRevenue = DB::table('bookings')->where('status', 'PAID')->sum('total_amount');
        $totalBookings = DB::table('bookings')->where('status', 'PAID')->count();
        $totalUsers = DB::table('users')->count();
        $totalMovies = DB::table('movies')->count();
        $revenueByMovie = DB::table('bookings')
            ->join('showtimes', 'showtimes.id', '=', 'bookings.showtime_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->where('bookings.status', 'PAID')
            ->select('movies.title', DB::raw('SUM(bookings.total_amount) as revenue'), DB::raw('COUNT(*) as bookings'))
            ->groupBy('movies.id', 'movies.title')
            ->orderBy('revenue', 'desc')
            ->get();
        return response()->json([
            'total_revenue'  => $totalRevenue,
            'total_bookings' => $totalBookings,
            'total_users'    => $totalUsers,
            'total_movies'   => $totalMovies,
            'revenue_by_movie' => $revenueByMovie,
        ]);
    });
});

// ===== TẠM: Dọn phim trùng + reset =====
Route::get('/maintenance/clean-movies/{secret}', function ($secret) {
    if ($secret !== 'cleanmovie2026') abort(403);
    DB::table('booking_seats')->whereIn('showtime_id', function($q) {
        $q->select('id')->from('showtimes')->where('id', '>', 12);
    })->delete();
    DB::table('showtimes')->where('id', '>', 12)->delete();
    DB::table('seats')->where('room_id', '>', 1)->delete();
    DB::table('rooms')->where('id', '>', 1)->delete();
    DB::table('cinemas')->where('id', '>', 1)->delete();
    DB::table('movies')->where('id', '>', 12)->delete();
    $cinema2 = DB::table('cinemas')->insertGetId(['name' => 'CGV Aeon Mall Long Biên', 'address' => 'Số 27 Cổ Linh, Long Biên, Hà Nội']);
    $cinema3 = DB::table('cinemas')->insertGetId(['name' => 'Lotte Cinema Landmark 72', 'address' => 'Keangnam Landmark72, Phạm Hùng, Hà Nội']);
    $room2 = DB::table('rooms')->insertGetId(['cinema_id' => $cinema2, 'name' => 'Phòng 1', 'rows_count' => 5, 'cols_count' => 8]);
    $room3 = DB::table('rooms')->insertGetId(['cinema_id' => $cinema3, 'name' => 'Phòng 1', 'rows_count' => 5, 'cols_count' => 8]);
    $rowTypes = ['A' => 'NORMAL', 'B' => 'NORMAL', 'C' => 'VIP', 'D' => 'VIP', 'E' => 'COUPLE'];
    foreach ([$room2, $room3] as $roomId) {
        foreach ($rowTypes as $row => $type) {
            for ($col = 1; $col <= 8; $col++) {
                DB::table('seats')->insert(['room_id' => $roomId, 'row_label' => $row, 'col_number' => $col, 'seat_type' => $type, 'is_active' => true]);
            }
        }
    }
    $movie5 = DB::table('movies')->insertGetId(['title' => 'Chiến Binh Bóng Đêm', 'duration_minutes' => 125, 'genre' => 'Hành động', 'description' => 'Cuộc chiến sinh tử giữa ánh sáng và bóng tối.', 'poster_url' => 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=400&q=80']);
    $movie6 = DB::table('movies')->insertGetId(['title' => 'Tình Yêu Mùa Hạ', 'duration_minutes' => 100, 'genre' => 'Tâm lý / Lãng mạn', 'description' => 'Câu chuyện tình yêu ngọt ngào dưới nắng hè.', 'poster_url' => 'https://images.unsplash.com/photo-1522869635100-9f4c5e86aa37?w=400&q=80']);
    $movie7 = DB::table('movies')->insertGetId(['title' => 'Vũ Trụ Song Song', 'duration_minutes' => 140, 'genre' => 'Khoa học viễn tưởng', 'description' => 'Hành trình xuyên không gian và thời gian.', 'poster_url' => 'https://images.unsplash.com/photo-1446776877081-d282a0f896e2?w=400&q=80']);
    $showtimes = [
        ['movie_id' => 1, 'room_id' => $room2, 'start_time' => '2026-07-12 10:00:00', 'end_time' => '2026-07-12 12:10:00', 'base_price' => 80000],
        ['movie_id' => 10, 'room_id' => $room2, 'start_time' => '2026-07-12 14:00:00', 'end_time' => '2026-07-12 15:55:00', 'base_price' => 85000],
        ['movie_id' => $movie5, 'room_id' => $room2, 'start_time' => '2026-07-12 18:00:00', 'end_time' => '2026-07-12 20:05:00', 'base_price' => 85000],
        ['movie_id' => 1, 'room_id' => $room3, 'start_time' => '2026-07-12 11:00:00', 'end_time' => '2026-07-12 13:10:00', 'base_price' => 90000],
        ['movie_id' => $movie6, 'room_id' => $room3, 'start_time' => '2026-07-12 15:00:00', 'end_time' => '2026-07-12 16:40:00', 'base_price' => 75000],
        ['movie_id' => $movie7, 'room_id' => $room3, 'start_time' => '2026-07-12 19:00:00', 'end_time' => '2026-07-12 21:20:00', 'base_price' => 90000],
    ];
    foreach ($showtimes as $st) {
        $showtimeId = DB::table('showtimes')->insertGetId($st);
        $seatIds = DB::table('seats')->where('room_id', $st['room_id'])->pluck('id');
        foreach ($seatIds as $seatId) {
            DB::table('booking_seats')->insert(['showtime_id' => $showtimeId, 'seat_id' => $seatId, 'status' => 'AVAILABLE']);
        }
    }
    return response()->json(['message' => 'Done!', 'cinemas' => DB::table('cinemas')->get(), 'movies' => DB::table('movies')->count()]);
});
