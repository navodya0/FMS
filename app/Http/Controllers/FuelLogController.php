<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FuelLog;
use App\Models\Vehicle;
use App\Models\Barrel;

class FuelLogController extends Controller
{
    public function index()
    {
        $barrels = Barrel::with('fuelLogs')->orderBy('barrel_number')->get();
        $vehicles = Vehicle::where('status', 'active')->orderBy('id')->get();

        // calculate balance per barrel
        foreach ($barrels as $barrel) {
            $refilled = $barrel->fuelLogs->sum('fuel_refilled_amount');
            $taken = $barrel->fuelLogs->sum('fuel_taken_count');

            $barrel->current_fuel = $refilled - $taken;
        }

        $fuelLogs = FuelLog::with(['vehicle','barrel'])->latest()->get();

        return view('fuel-logs.index', compact('barrels', 'vehicles', 'fuelLogs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'barrel_id'   => 'required|exists:barrels,id',
            'vehicle_id'  => 'required|exists:vehicles,id',
            'action_type' => 'required|in:refill,take',
            'amount'      => 'required|numeric|min:0.01',
            'action_date' => 'required|date',
        ]);

        $data = [
            'barrel_id'            => $request->barrel_id,
            'vehicle_id'           => $request->vehicle_id,
            'fuel_refilled_amount' => 0,
            'fuel_taken_count'     => 0,
            'fuel_refilled_date'   => null,
            'fuel_taken_date'      => null,
            'created_by'           => auth()->user()->name ?? 'system',
        ];

        if ($request->action_type === 'refill') {
            $data['fuel_refilled_amount'] = $request->amount;
            $data['fuel_refilled_date'] = $request->action_date;
        }

        if ($request->action_type === 'take') {
            $data['fuel_taken_count'] = $request->amount;
            $data['fuel_taken_date'] = $request->action_date;
        }

        FuelLog::create($data);

        return redirect()->route('fuel-logs.index')->with('success', 'Fuel log saved successfully.');
    }
}