<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('showtime_id')->constrained('showtimes');
            $table->enum('status', ['PENDING', 'PAID', 'CANCELLED', 'EXPIRED'])->default('PENDING');
            $table->decimal('total_amount', 10, 2);
            $table->timestamp('created_at')->useCurrent();
        });

        // BẢNG LÕI cho thuật toán giữ ghế: trạng thái ghế THEO TỪNG SUẤT CHIẾU
        Schema::create('booking_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('showtime_id')->constrained('showtimes');
            $table->foreignId('seat_id')->constrained('seats');
            $table->foreignId('booking_id')->nullable()->constrained('bookings');
            $table->enum('status', ['AVAILABLE', 'LOCKED', 'BOOKED'])->default('AVAILABLE');
            $table->foreignId('locked_by_id')->nullable()->constrained('users');
            $table->timestamp('locked_at')->nullable();
            $table->unique(['showtime_id', 'seat_id'], 'uq_showtime_seat');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings');
            $table->string('invoice_code', 50)->unique();
            $table->enum('payment_method', ['CASH', 'BANK_TRANSFER', 'MOMO', 'VNPAY']);
            $table->timestamp('paid_at')->nullable();
            $table->decimal('amount', 10, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('booking_seats');
        Schema::dropIfExists('bookings');
    }
};
