<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelLog extends Model
{
    protected $table = 'fuel_logs';

    protected $fillable = [
        'barrel_id',
        'vehicle_id',
        'fuel_refilled_amount',
        'fuel_taken_count',
        'fuel_refilled_date',
        'fuel_taken_date',
        'created_by',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function barrel()
    {
        return $this->belongsTo(Barrel::class);
    }
}