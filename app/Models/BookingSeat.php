<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingSeat extends Model
{
    public $timestamps = false;
    protected $table = 'booking_seats';
    protected $fillable = ['showtime_id', 'seat_id', 'booking_id', 'status', 'locked_by_id', 'locked_at'];

    public function seat()
    {
        return $this->belongsTo(Seat::class);
    }

    public function showtime()
    {
        return $this->belongsTo(Showtime::class);
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by_id');
    }
}
