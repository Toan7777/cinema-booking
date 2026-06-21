<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Showtime extends Model
{
    public $timestamps = false;
    protected $fillable = ['movie_id', 'room_id', 'start_time', 'end_time', 'base_price'];

    public function movie()
    {
        return $this->belongsTo(Movie::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
