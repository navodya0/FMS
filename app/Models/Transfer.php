<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = [
        'booking_number',
        'start_date',
        'end_date',
    ];
}
