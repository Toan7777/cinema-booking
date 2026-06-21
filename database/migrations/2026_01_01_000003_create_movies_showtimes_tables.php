<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->integer('duration_minutes');
            $table->string('genre', 100)->nullable();
            $table->string('poster_url', 255)->nullable();
            $table->text('description')->nullable();
        });

        Schema::create('showtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->constrained('movies');
            $table->foreignId('room_id')->constrained('rooms');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->decimal('base_price', 10, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showtimes');
        Schema::dropIfExists('movies');
    }
};
