<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\Carbon;

class VehicleAnalyticsController extends Controller
{
    public function index()
    {
        $vehicles = Vehicle::with('company')
            ->where('status', '!=', 'disabled')
            ->orderBy('reg_no')
            ->get();

        $rentals = Rental::with(['vehicle.company'])
            ->whereNotNull('vehicle_id')
            ->whereNotNull('arrival_date')
            ->whereNotNull('departure_date')
            ->orderBy('arrival_date')
            ->get();

        return view('vehicle-analytics.index', compact('rentals', 'vehicles'));
    }
}