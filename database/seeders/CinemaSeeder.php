<?php

namespace Database\Seeders;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Role;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Showtime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CinemaSeeder extends Seeder
{
    public function run(): void
    {
        // Roles
        foreach (['ADMIN', 'STAFF', 'CUSTOMER'] as $r) {
            Role::firstOrCreate(['name' => $r]);
        }

        // Rạp + phòng
        $cinema = Cinema::create(['name' => 'CGV Vincom Hà Đông', 'address' => '233 Quang Trung, Hà Đông, Hà Nội']);
        $room = Room::create(['cinema_id' => $cinema->id, 'name' => 'Phòng 1', 'rows_count' => 5, 'cols_count' => 8]);

        // 40 ghế: A-B thường, C-D VIP, E couple
        $rows = ['A' => 'NORMAL', 'B' => 'NORMAL', 'C' => 'VIP', 'D' => 'VIP', 'E' => 'COUPLE'];
        foreach ($rows as $row => $type) {
            for ($col = 1; $col <= 8; $col++) {
                Seat::create([
                    'room_id'    => $room->id,
                    'row_label'  => $row,
                    'col_number' => $col,
                    'seat_type'  => $type,
                ]);
            }
        }

        // Phim + suất chiếu
        $movie = Movie::create([
            'title'            => 'Avengers: Doomsday',
            'duration_minutes' => 150,
            'genre'            => 'Hành động/Giả tưởng',
            'description'      => 'Phim siêu anh hùng mới nhất.',
        ]);

        $showtime = Showtime::create([
            'movie_id'   => $movie->id,
            'room_id'    => $room->id,
            'start_time' => '2026-06-21 19:00:00',
            'end_time'   => '2026-06-21 21:30:00',
            'base_price' => 75000,
        ]);

        // Seed booking_seats: tạo sẵn 40 dòng AVAILABLE cho suất chiếu này
        $seatIds = Seat::where('room_id', $room->id)->pluck('id');
        $rowsToInsert = $seatIds->map(fn ($seatId) => [
            'showtime_id' => $showtime->id,
            'seat_id'     => $seatId,
            'status'      => 'AVAILABLE',
        ])->toArray();

        DB::table('booking_seats')->insert($rowsToInsert);
    }
}
