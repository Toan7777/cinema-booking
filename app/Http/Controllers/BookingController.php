<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Showtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    private function lockMinutes(): int
    {
        return (int) (env('SEAT_LOCK_MINUTES', 5));
    }

    /**
     * Lấy sơ đồ ghế của 1 suất chiếu, kèm trạng thái real-time.
     * Trước khi trả về, tự dọn các ghế LOCKED đã quá hạn.
     */
    public function getSeatMap(int $showtimeId)
    {
        $this->releaseExpiredSeats();

        $seats = DB::table('booking_seats as bs')
            ->join('seats as s', 's.id', '=', 'bs.seat_id')
            ->where('bs.showtime_id', $showtimeId)
            ->orderBy('s.row_label')
            ->orderBy('s.col_number')
            ->select(
                'bs.seat_id as seatId',
                's.row_label as rowLabel',
                's.col_number as colNumber',
                's.seat_type as seatType',
                'bs.status'
            )
            ->get();

        return response()->json($seats);
    }

    /**
     * THUẬT TOÁN GIỮ CHỖ (Seat Locking)
     *
     * Cơ chế: dùng câu UPDATE atomic trong 1 DB transaction.
     * UPDATE chỉ áp dụng cho các dòng đang ở trạng thái AVAILABLE.
     * Nếu số dòng bị ảnh hưởng (affected rows) < số ghế yêu cầu
     * => có ít nhất 1 ghế đã bị người khác giữ trước => rollback toàn bộ transaction.
     *
     * Vì InnoDB tự row-lock dòng dữ liệu trong lúc UPDATE, 2 request đến
     * gần như đồng thời sẽ được MySQL tự xếp hàng xử lý tuần tự, đảm bảo
     * không có 2 người cùng giữ được 1 ghế (chống race condition).
     */
    public function lockSeats(Request $request)
    {
        $data = $request->validate([
            'showtimeId' => 'required|integer|exists:showtimes,id',
            'seatIds'    => 'required|array|min:1|max:8',
            'seatIds.*'  => 'integer',
        ]);

        $userId = $request->user()->id;
        $showtimeId = $data['showtimeId'];
        $seatIds = $data['seatIds'];
        $lockMinutes = $this->lockMinutes();

        try {
            $result = DB::transaction(function () use ($showtimeId, $seatIds, $userId, $lockMinutes) {
                // 1. Dọn các ghế LOCKED đã quá hạn (trong cùng transaction)
                DB::table('booking_seats')
                    ->where('status', 'LOCKED')
                    ->where('locked_at', '<', now()->subMinutes($lockMinutes))
                    ->update(['status' => 'AVAILABLE', 'locked_by_id' => null, 'locked_at' => null]);

                // 2. Atomic UPDATE: chỉ khóa được ghế đang AVAILABLE
                $affected = DB::table('booking_seats')
                    ->where('showtime_id', $showtimeId)
                    ->whereIn('seat_id', $seatIds)
                    ->where('status', 'AVAILABLE')
                    ->update([
                        'status'       => 'LOCKED',
                        'locked_by_id' => $userId,
                        'locked_at'    => now(),
                    ]);

                if ($affected !== count($seatIds)) {
                    // Không khóa đủ tất cả ghế -> có người giữ trước -> hủy toàn bộ
                    throw new \RuntimeException('SEAT_CONFLICT');
                }

                return [
                    'lockedSeats' => $seatIds,
                    'expiresAt'   => now()->addMinutes($lockMinutes)->toIso8601String(),
                ];
            }, 3); // retry tối đa 3 lần nếu deadlock

            return response()->json([
                'message'   => 'Giữ ghế thành công',
                'lockedSeats' => $result['lockedSeats'],
                'expiresAt' => $result['expiresAt'],
            ]);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'SEAT_CONFLICT') {
                return response()->json([
                    'message' => 'Một hoặc nhiều ghế bạn chọn vừa được người khác giữ trước. Vui lòng chọn lại ghế khác.',
                ], 409);
            }
            throw $e;
        }
    }

    /** Nhả ghế thủ công (huỷ / rời trang trước khi thanh toán) */
    public function releaseSeats(Request $request)
    {
        $data = $request->validate([
            'showtimeId' => 'required|integer',
            'seatIds'    => 'required|array|min:1',
        ]);

        DB::table('booking_seats')
            ->where('showtime_id', $data['showtimeId'])
            ->whereIn('seat_id', $data['seatIds'])
            ->where('locked_by_id', $request->user()->id)
            ->update(['status' => 'AVAILABLE', 'locked_by_id' => null, 'locked_at' => null]);

        return response()->json(['message' => 'Đã nhả ghế']);
    }

    /**
     * Xác nhận đặt vé sau khi thanh toán thành công.
     * Chỉ chuyển ghế từ LOCKED (đúng user đang giữ) sang BOOKED.
     */
    public function confirmBooking(Request $request)
    {
        $data = $request->validate([
            'showtimeId' => 'required|integer|exists:showtimes,id',
            'seatIds'    => 'required|array|min:1',
        ]);

        $userId = $request->user()->id;
        $showtime = Showtime::findOrFail($data['showtimeId']);
        $totalAmount = $showtime->base_price * count($data['seatIds']);

        try {
            $bookingId = DB::transaction(function () use ($data, $userId, $totalAmount) {
                $bookingId = DB::table('bookings')->insertGetId([
                    'user_id'      => $userId,
                    'showtime_id'  => $data['showtimeId'],
                    'status'       => 'PENDING',
                    'total_amount' => $totalAmount,
                    'created_at'   => now(),
                ]);

                $affected = DB::table('booking_seats')
                    ->where('showtime_id', $data['showtimeId'])
                    ->whereIn('seat_id', $data['seatIds'])
                    ->where('status', 'LOCKED')
                    ->where('locked_by_id', $userId)
                    ->update(['status' => 'BOOKED', 'booking_id' => $bookingId]);

                if ($affected !== count($data['seatIds'])) {
                    throw new \RuntimeException('LOCK_EXPIRED');
                }

                DB::table('bookings')->where('id', $bookingId)->update(['status' => 'PAID']);

                return $bookingId;
            });

            return response()->json(['message' => 'Đặt vé thành công', 'bookingId' => $bookingId]);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'LOCK_EXPIRED') {
                $minutes = $this->lockMinutes();
                return response()->json([
                    'message' => "Ghế bạn giữ đã hết hạn (quá {$minutes} phút) hoặc đã bị hủy. Vui lòng đặt lại.",
                ], 400);
            }
            throw $e;
        }
    }

    /** Dọn ghế hết hạn - gọi từ scheduler (Console/Kernel.php) hoặc trước mỗi lần đọc sơ đồ ghế */
    public function releaseExpiredSeats(): void
    {
        DB::table('booking_seats')
            ->where('status', 'LOCKED')
            ->where('locked_at', '<', now()->subMinutes($this->lockMinutes()))
            ->update(['status' => 'AVAILABLE', 'booking_id' => null, 'locked_by_id' => null, 'locked_at' => null]);
    }
}
