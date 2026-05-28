<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rental;
use Carbon\CarbonPeriod;
use App\Models\VehicleFreeze;

class VehicleFreezeController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'vehicle_id'   => 'required|exists:vehicles,id',
            'start_date'   => 'required|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'reason'       => 'required|string|max:255',
            'other_reason' => 'nullable|string|max:255', // validate the "Other" input
            'remarks'      => 'nullable|string',
        ]);

        // Determine the actual reason
        $reason = $request->input('reason');
        if ($reason === 'Other') {
            $otherReason = $request->input('other_reason');
            if (!$otherReason) {
                return redirect()->back()
                                ->withInput()
                                ->withErrors(['other_reason' => 'Please provide a reason.']);
            }
            $reason = $otherReason;
        }

        // Create freeze
        $freeze = VehicleFreeze::create([
            'vehicle_id' => $request->vehicle_id,
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
            'reason'     => $reason,
            'remarks'    => $request->remarks,
        ]);

        // Log the action
        activity()
            ->causedBy(auth()->user())
            ->performedOn($freeze)
            ->withProperties([
                'vehicle_id' => $freeze->vehicle_id,
                'start_date' => $freeze->start_date,
                'end_date'   => $freeze->end_date,
                'reason'     => $freeze->reason,
                'remarks'    => $freeze->remarks,
                'ip'         => request()->ip(),
                'url'        => request()->fullUrl(),
                'method'     => request()->method(),
            ])
            ->log('Vehicle frozen');

        return redirect()->back()->with('success', 'Vehicle frozen successfully.');
    }

    public function destroy($id)
    {
        $freeze = VehicleFreeze::findOrFail($id);
        $freeze->delete();

        return response()->json(['message' => 'Vehicle unfrozen successfully']);
    }
    
    public function extend(Request $request)
    {
        $request->validate([
            'freeze_id'     => 'required|exists:vehicle_freezes,id',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'extend_reason' => 'required|string|max:255',
            'other_reason'  => 'nullable|string|max:255', // validate "Other" input
        ]);

        $freeze = VehicleFreeze::findOrFail($request->freeze_id);

        $extendReason = $request->input('extend_reason');
        if ($extendReason === 'Other') {
            $otherReason = $request->input('other_reason');
            if (!$otherReason) {
                return redirect()->back()
                                ->withInput()
                                ->withErrors(['other_reason' => 'Please provide a reason.']);
            }
            $extendReason = $otherReason;
        }

        if ($freeze->end_date !== $request->end_date) {
            $freeze->old_end_date = $freeze->end_date;
        }

        $freeze->end_date = $request->end_date;
        $freeze->extend_reason = $extendReason;
        $freeze->save();

        // Log the extension
        activity()
            ->causedBy(auth()->user())
            ->performedOn($freeze)
            ->withProperties([
                'vehicle_id'    => $freeze->vehicle_id,
                'old_end_date'  => $freeze->old_end_date,
                'new_end_date'  => $freeze->end_date,
                'extend_reason' => $freeze->extend_reason,
            ])
            ->log('Vehicle freeze extended');

        return redirect()->back()->with('success', 'Freeze extended successfully.');
    }

    public function bookedDates($vehicleId)
    {
        $rentals = Rental::where('vehicle_id', $vehicleId)
            ->whereNotNull('arrival_date')
            ->whereNotNull('departure_date')
            ->get(['arrival_date', 'departure_date', 'booking_number']);

        $disabledDates = [];
        $bookedRanges = [];

        foreach ($rentals as $rental) {
            $start = \Carbon\Carbon::parse($rental->arrival_date)->format('Y-m-d');
            $end   = \Carbon\Carbon::parse($rental->departure_date)->format('Y-m-d');

            $bookedRanges[] = [
                'from' => $start,
                'to' => $end,
                'booking_number' => $rental->booking_number,
            ];

            foreach (\Carbon\CarbonPeriod::create($start, $end) as $date) {
                $disabledDates[] = $date->format('Y-m-d');
            }
        }

        return response()->json([
            'disabled_dates' => array_values(array_unique($disabledDates)),
            'booked_ranges' => $bookedRanges,
        ]);
    }

}
