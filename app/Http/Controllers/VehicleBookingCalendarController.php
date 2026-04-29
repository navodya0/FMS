<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleBookingCalendarController extends Controller
{
    public function index(Request $request)
    {
        $monthInput = $request->get('month', now()->format('Y-m'));

        $month = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        $days = collect(CarbonPeriod::create($startOfMonth, $endOfMonth));
        $totalDays = $days->count();

        $calendar = $this->buildCalendarData($startOfMonth, $endOfMonth, $totalDays);

        return view('vehicle-booking-calendar.index', [
            'calendar' => $calendar,
            'days' => $days,
            'month' => $month,
            'totalDays' => $totalDays,
            'selectedMonth' => $month->format('Y-m'),
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
        ]);
    }

public function exportCsv(Request $request): StreamedResponse
{
    $monthInput = $request->get('month', now()->format('Y-m'));

    $month = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
    $startOfMonth = $month->copy()->startOfMonth();
    $endOfMonth = $month->copy()->endOfMonth();

    $totalDays = $month->daysInMonth;
    $calendar = $this->buildCalendarData($startOfMonth, $endOfMonth, $totalDays);

    $fileName = 'vehicle_booking_calendar_' . $month->format('Y_m') . '.csv';

    return response()->streamDownload(function () use ($calendar, $month, $totalDays) {
        $handle = fopen('php://output', 'w');

        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($handle, ['Vehicle Booking Calendar Report']);
        fputcsv($handle, ['Month', $month->format('F Y')]);
        fputcsv($handle, ['Total Days In Month', $totalDays]);
        fputcsv($handle, []);
        fputcsv($handle, [
            'Vehicle No',
            'Vehicle Status',
            'Rental Status',
            'Booked From',
            'Booked To',
            'Company',
            'Usage %',
            'Utilization Status',
        ]);

        foreach ($calendar as $row) {
            $vehicleNo = $row['vehicle']->reg_no ?? ('Vehicle #' . $row['vehicle']->id);
            $vehicleStatus = ucfirst($row['displayStatus']);
            $usagePercent = (float) $row['usagePercent'];
            $usage = $usagePercent . '%';
            $utilizationStatus = $this->getUtilizationStatus($usagePercent);

            if (count($row['ranges']) === 0) {
                fputcsv($handle, [
                    $vehicleNo,
                    $vehicleStatus,
                    'Not Booked',
                    '-',
                    '-',
                    '-',
                    $usage,
                    $utilizationStatus,
                ]);

                continue;
            }

            foreach ($row['ranges'] as $range) {
                $rental = $range['rental'];

                $companyName = $rental->company->name
                    ?? $rental->company->company_name
                    ?? '-';

                fputcsv($handle, [
                    $vehicleNo,
                    $vehicleStatus,
                    $rental->status ? ucfirst($rental->status) : 'Booked',
                    Carbon::parse($rental->arrival_date)->format('Y-m-d'),
                    Carbon::parse($rental->departure_date)->format('Y-m-d'),
                    $companyName,
                    $usage,
                    $utilizationStatus,
                ]);
            }
        }

        fputcsv($handle, []);
        fputcsv($handle, ['Status Rules']);
        fputcsv($handle, ['Above 75%', 'Excellent']);
        fputcsv($handle, ['65% - 75%', 'Good']);
        fputcsv($handle, ['50% - 64.99%', 'Fair']);
        fputcsv($handle, ['1% - 49.99%', 'Under Utilized']);
        fputcsv($handle, ['0%', 'Not Utilized']);
        fputcsv($handle, []);
        fputcsv($handle, ['Generated At', now()->format('Y-m-d H:i:s')]);

        fclose($handle);
    }, $fileName, [
        'Content-Type' => 'text/csv; charset=UTF-8',
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



    private function buildCalendarData($startOfMonth, $endOfMonth, $totalDays)
    {
        $freezedVehicleIds = DB::table('vehicle_freezes')
            ->pluck('vehicle_id')
            ->toArray();

        $vehicles = Vehicle::orderBy('reg_no', 'asc')->get();

        $rentals = Rental::with('company')
            ->whereNull('deleted_at')
            ->whereNotNull('vehicle_id')
            ->where(function ($query) use ($startOfMonth, $endOfMonth) {
                $query->whereBetween('arrival_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('departure_date', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($q) use ($startOfMonth, $endOfMonth) {
                        $q->where('arrival_date', '<=', $startOfMonth)
                            ->where('departure_date', '>=', $endOfMonth);
                    });
            })
            ->orderBy('arrival_date')
            ->get()
            ->groupBy('vehicle_id');

        return $vehicles->map(function ($vehicle) use ($rentals, $startOfMonth, $endOfMonth, $totalDays, $freezedVehicleIds) {
            $vehicleRentals = $rentals->get($vehicle->id, collect());

            $ranges = [];
            $usedDates = [];

            foreach ($vehicleRentals as $rental) {
                if (!$rental->arrival_date || !$rental->departure_date) {
                    continue;
                }

                $arrival = Carbon::parse($rental->arrival_date)->startOfDay();
                $departure = Carbon::parse($rental->departure_date)->startOfDay();

                $rangeStart = $arrival->greaterThan($startOfMonth) ? $arrival : $startOfMonth;
                $rangeEnd = $departure->lessThan($endOfMonth) ? $departure : $endOfMonth;

                foreach (CarbonPeriod::create($rangeStart, $rangeEnd) as $date) {
                    $usedDates[$date->format('Y-m-d')] = true;
                }

                $ranges[] = [
                    'rental' => $rental,
                    'start_day' => $rangeStart->day,
                    'end_day' => $rangeEnd->day,
                ];
            }

            $isFreezed = in_array($vehicle->id, $freezedVehicleIds);

            if ($isFreezed) {
                $displayStatus = 'freezed';
            } elseif ($vehicle->status === 'active') {
                $displayStatus = 'active';
            } else {
                $displayStatus = 'disabled';
            }

            return [
                'vehicle' => $vehicle,
                'ranges' => $ranges,
                'usagePercent' => $totalDays > 0 ? round((count($usedDates) / $totalDays) * 100, 2) : 0,
                'displayStatus' => $displayStatus,
            ];
        });
    }
}