<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    public $timestamps = false;
    protected $fillable = ['room_id', 'row_label', 'col_number', 'seat_type', 'is_active'];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
