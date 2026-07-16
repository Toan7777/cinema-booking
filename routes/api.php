<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\MovieController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

Route::get('/cinemas', function () {
    return response()->json(DB::table('cinemas')->get());
});

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

// ===== ADMIN ROUTES =====
Route::middleware(['auth:sanctum', 'role:ADMIN'])->prefix('admin')->group(function () {

    // --- CINEMAS CRUD ---
    Route::get('/cinemas', function () {
        return response()->json(DB::table('cinemas')->get());
    });
    Route::post('/cinemas', function (\Illuminate\Http\Request $req) {
        $data = $req->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
        ]);
        $id = DB::table('cinemas')->insertGetId($data);
        return response()->json(DB::table('cinemas')->find($id), 201);
    });
    Route::patch('/cinemas/{id}', function (\Illuminate\Http\Request $req, $id) {
        $data = $req->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string|max:255',
        ]);
        DB::table('cinemas')->where('id', $id)->update($data);
        return response()->json(DB::table('cinemas')->find($id));
    });
    Route::delete('/cinemas/{id}', function ($id) {
        $hasRooms = DB::table('rooms')->where('cinema_id', $id)->exists();
        if ($hasRooms) {
            return response()->json(['message' => 'Không thể xóa: rạp còn phòng chiếu. Hãy xóa phòng trước.'], 422);
        }
        DB::table('cinemas')->where('id', $id)->delete();
        return response()->json(['message' => 'Đã xóa rạp']);
    });

    // --- ROOMS CRUD ---
    Route::get('/rooms', function () {
        return response()->json(
            DB::table('rooms')
                ->join('cinemas', 'cinemas.id', '=', 'rooms.cinema_id')
                ->select('rooms.*', 'cinemas.name as cinema_name')
                ->orderBy('rooms.cinema_id')
                ->get()
        );
    });
    Route::get('/cinemas/{cinemaId}/rooms', function ($cinemaId) {
        return response()->json(DB::table('rooms')->where('cinema_id', $cinemaId)->get());
    });
    Route::post('/rooms', function (\Illuminate\Http\Request $req) {
        $data = $req->validate([
            'cinema_id' => 'required|integer|exists:cinemas,id',
            'name' => 'required|string|max:255',
            'rows_count' => 'required|integer|min:1|max:26',
            'cols_count' => 'required|integer|min:1|max:40',
        ]);
        return DB::transaction(function () use ($data) {
            $roomId = DB::table('rooms')->insertGetId($data);
            $rowLabels = range('A', chr(64 + $data['rows_count']));
            $seatRows = [];
            foreach ($rowLabels as $i => $row) {
                $type = $i >= count($rowLabels) - 2 ? 'VIP' : 'NORMAL';
                if ($i === count($rowLabels) - 1) $type = 'COUPLE';
                for ($col = 1; $col <= $data['cols_count']; $col++) {
                    $seatRows[] = [
                        'room_id' => $roomId, 'row_label' => $row, 'col_number' => $col,
                        'seat_type' => $type, 'is_active' => true,
                    ];
                }
            }
            DB::table('seats')->insert($seatRows);
            return response()->json(DB::table('rooms')->find($roomId), 201);
        });
    });
    Route::delete('/rooms/{id}', function ($id) {
        $hasShowtimes = DB::table('showtimes')->where('room_id', $id)->exists();
        if ($hasShowtimes) {
            return response()->json(['message' => 'Không thể xóa: phòng còn suất chiếu.'], 422);
        }
        DB::table('seats')->where('room_id', $id)->delete();
        DB::table('rooms')->where('id', $id)->delete();
        return response()->json(['message' => 'Đã xóa phòng']);
    });

    // --- SHOWTIMES CRUD ---
    Route::get('/showtimes', function () {
        return response()->json(
            DB::table('showtimes')
                ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
                ->join('rooms', 'rooms.id', '=', 'showtimes.room_id')
                ->join('cinemas', 'cinemas.id', '=', 'rooms.cinema_id')
                ->select(
                    'showtimes.*',
                    'movies.title as movie_title',
                    'rooms.name as room_name',
                    'cinemas.name as cinema_name',
                    'cinemas.id as cinema_id'
                )
                ->orderBy('showtimes.start_time', 'desc')
                ->get()
        );
    });
    Route::post('/showtimes', function (\Illuminate\Http\Request $req) {
        $data = $req->validate([
            'movie_id'   => 'required|integer|exists:movies,id',
            'room_id'    => 'required|integer|exists:rooms,id',
            'start_time' => 'required|date',
            'end_time'   => 'required|date|after:start_time',
            'base_price' => 'required|numeric|min:0',
        ]);
        return DB::transaction(function () use ($data) {
            $showtimeId = DB::table('showtimes')->insertGetId($data);
            $seatIds = DB::table('seats')->where('room_id', $data['room_id'])->pluck('id');
            $rows = $seatIds->map(fn ($seatId) => [
                'showtime_id' => $showtimeId, 'seat_id' => $seatId, 'status' => 'AVAILABLE',
            ])->toArray();
            if (!empty($rows)) {
                DB::table('booking_seats')->insert($rows);
            }
            return response()->json(DB::table('showtimes')->find($showtimeId), 201);
        });
    });
    Route::patch('/showtimes/{id}', function (\Illuminate\Http\Request $req, $id) {
        $data = $req->validate([
            'start_time' => 'sometimes|required|date',
            'end_time'   => 'sometimes|required|date',
            'base_price' => 'sometimes|required|numeric|min:0',
        ]);
        DB::table('showtimes')->where('id', $id)->update($data);
        return response()->json(DB::table('showtimes')->find($id));
    });
    Route::delete('/showtimes/{id}', function ($id) {
        $hasBookings = DB::table('bookings')->where('showtime_id', $id)->where('status', 'PAID')->exists();
        if ($hasBookings) {
            return response()->json(['message' => 'Không thể xóa: suất chiếu đã có vé đặt.'], 422);
        }
        DB::table('booking_seats')->where('showtime_id', $id)->delete();
        DB::table('showtimes')->where('id', $id)->delete();
        return response()->json(['message' => 'Đã xóa suất chiếu']);
    });

    // --- BOOKINGS ---
    Route::get('/bookings', function () {
        return response()->json(
            DB::table('bookings')
                ->join('users', 'users.id', '=', 'bookings.user_id')
                ->join('showtimes', 'showtimes.id', '=', 'bookings.showtime_id')
                ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
                ->join('rooms', 'rooms.id', '=', 'showtimes.room_id')
                ->join('cinemas', 'cinemas.id', '=', 'rooms.cinema_id')
                ->select(
                    'bookings.*',
                    'users.full_name as user_name',
                    'users.email as user_email',
                    'movies.title as movie_title',
                    'showtimes.start_time',
                    'cinemas.name as cinema_name'
                )
                ->orderBy('bookings.created_at', 'desc')
                ->get()
        );
    });

    // --- STATS / DOANH THU ---
    Route::get('/stats', function () {
        $totalRevenue = DB::table('bookings')->where('status', 'PAID')->sum('total_amount');
        $totalBookings = DB::table('bookings')->where('status', 'PAID')->count();
        $totalUsers = DB::table('users')->count();
        $totalMovies = DB::table('movies')->count();

        $revenueByMovie = DB::table('bookings')
            ->join('showtimes', 'showtimes.id', '=', 'bookings.showtime_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->where('bookings.status', 'PAID')
            ->select('movies.title', DB::raw('SUM(bookings.total_amount) as revenue'), DB::raw('COUNT(*) as bookings_count'))
            ->groupBy('movies.id', 'movies.title')
            ->orderBy('revenue', 'desc')
            ->get();

        $revenueByCinema = DB::table('bookings')
            ->join('showtimes', 'showtimes.id', '=', 'bookings.showtime_id')
            ->join('rooms', 'rooms.id', '=', 'showtimes.room_id')
            ->join('cinemas', 'cinemas.id', '=', 'rooms.cinema_id')
            ->where('bookings.status', 'PAID')
            ->select('cinemas.name', DB::raw('SUM(bookings.total_amount) as revenue'), DB::raw('COUNT(*) as bookings_count'))
            ->groupBy('cinemas.id', 'cinemas.name')
            ->orderBy('revenue', 'desc')
            ->get();

        $recentBookings = DB::table('bookings')
            ->join('users', 'users.id', '=', 'bookings.user_id')
            ->join('showtimes', 'showtimes.id', '=', 'bookings.showtime_id')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->where('bookings.status', 'PAID')
            ->select('bookings.id', 'bookings.total_amount', 'bookings.created_at', 'users.full_name as user_name', 'movies.title as movie_title')
            ->orderBy('bookings.created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'total_revenue'     => $totalRevenue,
            'total_bookings'    => $totalBookings,
            'total_users'       => $totalUsers,
            'total_movies'      => $totalMovies,
            'revenue_by_movie'  => $revenueByMovie,
            'revenue_by_cinema' => $revenueByCinema,
            'recent_bookings'   => $recentBookings,
        ]);
    });
});

// ===== TẠM: Thêm rạp + phim mới =====
Route::get('/maintenance/add-cinemas/{secret}', function ($secret) {
    if ($secret !== 'addcinema2026') abort(403);

    $seedSeats = function (int $roomId): void {
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
    };

    $seedBookingSeats = function (int $showtimeId, int $roomId): void {
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
    };

    return DB::transaction(function () use ($seedSeats, $seedBookingSeats) {
        $cinema2 = DB::table('cinemas')->insertGetId([
            'name' => 'CGV Aeon Mall Long Biên',
            'address' => 'Số 27 Cổ Linh, Long Biên, Hà Nội',
        ]);
        $cinema3 = DB::table('cinemas')->insertGetId([
            'name' => 'Lotte Cinema Landmark 72',
            'address' => 'Keangnam Landmark72, Phạm Hùng, Hà Nội',
        ]);

        $room2 = DB::table('rooms')->insertGetId(['cinema_id' => $cinema2, 'name' => 'Phòng 1', 'rows_count' => 5, 'cols_count' => 8]);
        $room3 = DB::table('rooms')->insertGetId(['cinema_id' => $cinema3, 'name' => 'Phòng 1', 'rows_count' => 5, 'cols_count' => 8]);

        $seedSeats($room2);
        $seedSeats($room3);

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

        $showtimeData = [
            ['movie_id' => 1,       'room_id' => $room2, 'start_time' => '2026-07-10 10:00:00', 'end_time' => '2026-07-10 12:10:00', 'base_price' => 80000],
            ['movie_id' => $movie5, 'room_id' => $room2, 'start_time' => '2026-07-10 14:00:00', 'end_time' => '2026-07-10 16:05:00', 'base_price' => 85000],
            ['movie_id' => $movie6, 'room_id' => $room3, 'start_time' => '2026-07-10 15:00:00', 'end_time' => '2026-07-10 16:40:00', 'base_price' => 75000],
            ['movie_id' => $movie7, 'room_id' => $room3, 'start_time' => '2026-07-10 19:00:00', 'end_time' => '2026-07-10 21:20:00', 'base_price' => 90000],
        ];

        foreach ($showtimeData as $st) {
            $showtimeId = DB::table('showtimes')->insertGetId($st);
            $seedBookingSeats($showtimeId, $st['room_id']);
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

    $seedSeats = function (int $roomId): void {
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
    };

    $seedBookingSeats = function (int $showtimeId, int $roomId): void {
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
    };

    return DB::transaction(function () use ($seedSeats, $seedBookingSeats) {
        DB::table('booking_seats')->whereIn('showtime_id', function ($q) {
            $q->select('id')->from('showtimes')->where('id', '>', 12);
        })->delete();
        DB::table('showtimes')->where('id', '>', 12)->delete();
        DB::table('seats')->where('room_id', '>', 1)->delete();
        DB::table('rooms')->where('id', '>', 1)->delete();
        DB::table('cinemas')->where('id', '>', 1)->delete();
        DB::table('movies')->where('id', '>', 12)->delete();

        $cinema2 = DB::table('cinemas')->insertGetId([
            'name' => 'CGV Aeon Mall Long Biên',
            'address' => 'Số 27 Cổ Linh, Long Biên, Hà Nội',
        ]);
        $cinema3 = DB::table('cinemas')->insertGetId([
            'name' => 'Lotte Cinema Landmark 72',
            'address' => 'Keangnam Landmark72, Phạm Hùng, Hà Nội',
        ]);

        $room2 = DB::table('rooms')->insertGetId(['cinema_id' => $cinema2, 'name' => 'Phòng 1', 'rows_count' => 5, 'cols_count' => 8]);
        $room3 = DB::table('rooms')->insertGetId(['cinema_id' => $cinema3, 'name' => 'Phòng 1', 'rows_count' => 5, 'cols_count' => 8]);

        $seedSeats($room2);
        $seedSeats($room3);

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

        $showtimes = [
            ['movie_id' => 1,       'room_id' => $room2, 'start_time' => '2026-07-12 10:00:00', 'end_time' => '2026-07-12 12:10:00', 'base_price' => 80000],
            ['movie_id' => 10,      'room_id' => $room2, 'start_time' => '2026-07-12 14:00:00', 'end_time' => '2026-07-12 15:55:00', 'base_price' => 85000],
            ['movie_id' => $movie5, 'room_id' => $room2, 'start_time' => '2026-07-12 18:00:00', 'end_time' => '2026-07-12 20:05:00', 'base_price' => 85000],
            ['movie_id' => 1,       'room_id' => $room3, 'start_time' => '2026-07-12 11:00:00', 'end_time' => '2026-07-12 13:10:00', 'base_price' => 90000],
            ['movie_id' => $movie6, 'room_id' => $room3, 'start_time' => '2026-07-12 15:00:00', 'end_time' => '2026-07-12 16:40:00', 'base_price' => 75000],
            ['movie_id' => $movie7, 'room_id' => $room3, 'start_time' => '2026-07-12 19:00:00', 'end_time' => '2026-07-12 21:20:00', 'base_price' => 90000],
        ];

        foreach ($showtimes as $st) {
            $showtimeId = DB::table('showtimes')->insertGetId($st);
            $seedBookingSeats($showtimeId, $st['room_id']);
        }

        return response()->json([
            'message' => 'Dọn + seed sạch thành công!',
            'cinemas' => DB::table('cinemas')->get(),
            'movies_count' => DB::table('movies')->count(),
            'showtimes_count' => DB::table('showtimes')->count(),
        ]);
    });
});

// ===== LỊCH CHIẾU ĐỘNG: tự sinh suất chiếu cho 7 ngày tới =====
// Cách hoạt động:
// 1) "Mẫu" suất chiếu = các tổ hợp (phim, phòng, giờ:phút trong ngày, giá vé)
//    đang có trong bảng showtimes, bất kể đang gắn ngày nào.
// 2) Với mỗi mẫu, đảm bảo có đủ suất chiếu cho 7 ngày kể từ hôm nay
//    (thiếu ngày nào thì tạo ngày đó — giữ nguyên giờ, chỉ đổi ngày),
//    đồng thời tự sinh booking_seats để có thể đặt ghế ngay.
// 3) Dọn các suất chiếu đã QUA ngày hôm nay — trừ suất đã có vé PAID
//    (giữ lại để không phá lịch sử đơn hàng).
// Gọi route này 1 lần để bootstrap, sau đó lên lịch gọi mỗi ngày 1 lần
// (qua cron ngoài, vì Render free không có cron sẵn) để lịch luôn "lăn" đúng 7 ngày.
Route::get('/maintenance/roll-showtimes/{secret}', function ($secret) {
    if ($secret !== 'rollshowtime2026') abort(403);

    return DB::transaction(function () {
        $today = Carbon::today();
        $windowDates = collect(range(0, 6))->map(fn ($i) => $today->copy()->addDays($i)->toDateString());

        // Lấy toàn bộ suất chiếu hiện có kèm thời lượng phim.
        // Xử lý bằng PHP (không dùng hàm ngày giờ riêng của từng DB engine)
        // để chạy được trên cả MySQL lẫn PostgreSQL.
        $allShowtimes = DB::table('showtimes')
            ->join('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->select('showtimes.movie_id', 'showtimes.room_id', 'showtimes.base_price', 'showtimes.start_time', 'movies.duration_minutes')
            ->get();

        // Rút ra "mẫu" (mỗi tổ hợp phim+phòng+giờ+giá chỉ giữ 1 bản)
        // và tập hợp các suất đã tồn tại — làm trong 1 lần lặp để đỡ tốn công.
        $templates = [];
        $existingKeys = [];
        foreach ($allShowtimes as $st) {
            $timeOfDay = Carbon::parse($st->start_time)->format('H:i:s');
            $tplKey = $st->movie_id . '|' . $st->room_id . '|' . $timeOfDay . '|' . $st->base_price;
            $templates[$tplKey] = [
                'movie_id' => $st->movie_id,
                'room_id' => $st->room_id,
                'base_price' => $st->base_price,
                'time_of_day' => $timeOfDay,
                'duration_minutes' => $st->duration_minutes,
            ];
            $dateOfDay = Carbon::parse($st->start_time)->format('Y-m-d');
            $existingKeys[$dateOfDay . '|' . $st->movie_id . '|' . $st->room_id . '|' . $timeOfDay] = true;
        }

        // Gom TẤT CẢ suất chiếu cần tạo mới vào 1 mảng — không ghi DB trong vòng lặp
        $rowsToInsert = [];
        foreach ($windowDates as $dateStr) {
            foreach ($templates as $tpl) {
                $lookupKey = $dateStr . '|' . $tpl['movie_id'] . '|' . $tpl['room_id'] . '|' . $tpl['time_of_day'];
                if (isset($existingKeys[$lookupKey])) {
                    continue;
                }

                $startTime = Carbon::parse("$dateStr {$tpl['time_of_day']}");
                $endTime = $startTime->copy()->addMinutes((int) $tpl['duration_minutes']);

                $rowsToInsert[] = [
                    'movie_id'   => $tpl['movie_id'],
                    'room_id'    => $tpl['room_id'],
                    'start_time' => $startTime->toDateTimeString(),
                    'end_time'   => $endTime->toDateTimeString(),
                    'base_price' => $tpl['base_price'],
                ];
                $existingKeys[$lookupKey] = true; // tránh tạo trùng ngay trong vòng lặp này
            }
        }

        // Ghi hàng loạt theo lô 200 dòng/lần thay vì từng dòng một
        collect($rowsToInsert)->chunk(200)->each(function ($chunk) {
            DB::table('showtimes')->insert($chunk->toArray());
        });

        // Tìm lại đúng các suất VỪA tạo (những suất trong cửa sổ 7 ngày mà
        // chưa có booking_seats — vì suất cũ luôn đã được seed ghế từ trước)
        $windowStart = $windowDates->first() . ' 00:00:00';
        $windowEnd = Carbon::parse($windowDates->last())->endOfDay()->toDateTimeString();
        $newShowtimes = DB::table('showtimes')
            ->whereBetween('start_time', [$windowStart, $windowEnd])
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('booking_seats')
                    ->whereColumn('booking_seats.showtime_id', 'showtimes.id');
            })
            ->get(['id', 'room_id']);

        // Lấy ghế theo phòng 1 lần duy nhất, rồi build toàn bộ booking_seats để ghi 1 lượt
        $roomIds = $newShowtimes->pluck('room_id')->unique();
        $seatsByRoom = DB::table('seats')->whereIn('room_id', $roomIds)->get(['id', 'room_id'])->groupBy('room_id');

        $bookingSeatRows = [];
        foreach ($newShowtimes as $st) {
            foreach ($seatsByRoom[$st->room_id] ?? [] as $seat) {
                $bookingSeatRows[] = [
                    'showtime_id' => $st->id,
                    'seat_id'     => $seat->id,
                    'status'      => 'AVAILABLE',
                ];
            }
        }
        collect($bookingSeatRows)->chunk(500)->each(function ($chunk) {
            DB::table('booking_seats')->insert($chunk->toArray());
        });

        // Dọn các suất chiếu đã qua ngày hôm nay, trừ suất đã có vé PAID
        $pastShowtimeIds = DB::table('showtimes')
            ->where('start_time', '<', $today->toDateTimeString())
            ->pluck('id');

        $protectedIds = DB::table('bookings')
            ->whereIn('showtime_id', $pastShowtimeIds)
            ->where('status', 'PAID')
            ->pluck('showtime_id')
            ->unique();

        $deletableIds = $pastShowtimeIds->diff($protectedIds)->values();

        DB::table('booking_seats')->whereIn('showtime_id', $deletableIds)->delete();
        DB::table('showtimes')->whereIn('id', $deletableIds)->delete();

        return response()->json([
            'message'                 => 'Đã cập nhật lịch chiếu cho 7 ngày tới',
            'templates_found'         => count($templates),
            'window'                  => [$windowDates->first(), $windowDates->last()],
            'showtimes_created'       => count($rowsToInsert),
            'booking_seats_created'   => count($bookingSeatRows),
            'old_showtimes_removed'   => $deletableIds->count(),
            'old_showtimes_kept_paid' => $protectedIds->count(),
        ]);
    });
});
