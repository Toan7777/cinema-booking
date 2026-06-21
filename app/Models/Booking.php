<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'showtime_id', 'status', 'total_amount', 'created_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function showtime()
    {
        return $this->belongsTo(Showtime::class);
    }

    public function bookingSeats()
    {
        return $this->hasMany(BookingSeat::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
