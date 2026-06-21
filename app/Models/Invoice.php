<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    public $timestamps = false;
    protected $fillable = ['booking_id', 'invoice_code', 'payment_method', 'paid_at', 'amount'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
