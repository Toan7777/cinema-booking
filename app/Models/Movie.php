<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    public $timestamps = false;
    protected $fillable = ['title', 'duration_minutes', 'genre', 'poster_url', 'description'];

    public function showtimes()
    {
        return $this->hasMany(Showtime::class);
    }
}
