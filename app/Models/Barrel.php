<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barrel extends Model
{
    protected $fillable = [
        'barrel_number',
        'capacity',
        'status',
    ];

    public function fuelLogs()
    {
        return $this->hasMany(FuelLog::class);
    }
}