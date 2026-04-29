<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleUtilizationController extends Controller
{
    public function summary(Request $request)
    {
        $request->validate([
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        if ($request->filled('date')) {
            $from = Carbon::parse($request->date)->startOfDay();
            $to = Carbon::parse($request->date)->startOfDay();
        } else {
            $from = Carbon::parse($request->get('from', now()->startOfMonth()))->startOfDay();
            $to = Carbon::parse($request->get('to', now()->endOfMonth()))->startOfDay();
        }

        if ($to->lt($from)) {
            return response()->json([
                'message' => 'Invalid date range',
            ], 422);
        }

        $periodDays = collect(CarbonPeriod::create($from, $to))->count();

        $freezedVehicleIds = DB::table('vehicle_freezes')
            ->pluck('vehicle_id')
            ->toArray();

        $vehicles = Vehicle::with('company')
            ->orderBy('reg_no')
            ->get();

        $rentals = Rental::whereNull('deleted_at')
            ->whereNotNull('vehicle_id')
            ->whereNotNull('arrival_date')
            ->whereNotNull('departure_date')
            ->where(function ($query) use ($from, $to) {
                $query->whereDate('arrival_date', '<=', $to)
                    ->whereDate('departure_date', '>=', $from);
            })
            ->get()
            ->groupBy('vehicle_id');

        $summary = $vehicles->map(function ($vehicle) use ($rentals, $freezedVehicleIds, $from, $to, $periodDays) {

            $vehicleRentals = $rentals->get($vehicle->id, collect());

            $usedDates = [];

            foreach ($vehicleRentals as $rental) {
                $arrival = Carbon::parse($rental->arrival_date)->startOfDay();
                $departure = Carbon::parse($rental->departure_date)->startOfDay();

                $rangeStart = $arrival->greaterThan($from) ? $arrival : $from;
                $rangeEnd = $departure->lessThan($to) ? $departure : $to;

                foreach (CarbonPeriod::create($rangeStart, $rangeEnd) as $date) {
                    $usedDates[$date->format('Y-m-d')] = true;
                }
            }

            $usedDays = count($usedDates);

            $usagePercent = $periodDays > 0
                ? round(($usedDays / $periodDays) * 100, 2)
                : 0;

            if (in_array($vehicle->id, $freezedVehicleIds)) {
                $vehicleStatus = 'freezed';
            } elseif ($vehicle->status === 'active') {
                $vehicleStatus = 'active';
            } else {
                $vehicleStatus = 'disabled';
            }

            return [
                'vehicle_id' => $vehicle->id,
                'vehicle_no' => $vehicle->reg_no,
                'vehicle_status' => $vehicleStatus,
                'company' => $vehicle->company->name
                    ?? $vehicle->company->company_name
                    ?? null,
                'used_days' => $usedDays,
                'total_days' => $periodDays,
                'usage_percent' => $usagePercent,
                'utilization_status' => $this->getUtilizationStatus($usagePercent),
                'is_utilized' => $usedDays > 0,
            ];
        });

        return response()->json([
            'period' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'total_days' => $periodDays,
            ],
            'totals' => [
                'vehicles' => $summary->count(),
                'utilized_vehicles' => $summary->where('is_utilized', true)->count(),
                'not_utilized_vehicles' => $summary->where('is_utilized', false)->count(),
            ],
            'data' => $summary->values(),
        ]);
    }

    private function getUtilizationStatus(float $usagePercent): string
    {
        if ($usagePercent <= 0) {
            return 'Not Utilized';
        }

        if ($usagePercent > 75) {
            return 'Excellent';
        }

        if ($usagePercent >= 65) {
            return 'Good';
        }

        if ($usagePercent >= 50) {
            return 'Fair';
        }

        return 'Under Utilized';
    }
}