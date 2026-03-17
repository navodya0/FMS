<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleQrImage extends Model
{
    protected $fillable = [
        'vehicle_id',
        'qr_image',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}