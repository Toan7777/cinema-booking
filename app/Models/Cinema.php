<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cinema extends Model
{
    public $timestamps = false;
    protected $fillable = ['name', 'address'];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}
