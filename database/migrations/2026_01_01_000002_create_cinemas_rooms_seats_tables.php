<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cinemas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('address', 255)->nullable();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cinema_id')->constrained('cinemas');
            $table->string('name', 50);
            $table->integer('rows_count');
            $table->integer('cols_count');
        });

        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms');
            $table->char('row_label', 2);
            $table->integer('col_number');
            $table->enum('seat_type', ['NORMAL', 'VIP', 'COUPLE'])->default('NORMAL');
            $table->boolean('is_active')->default(true);
            $table->unique(['room_id', 'row_label', 'col_number'], 'uq_seat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('cinemas');
    }
};
