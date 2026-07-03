<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\VehicleCategory;
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

        $vehicleTypes = VehicleType::orderBy('type_name')->get();

        $selectedTypeId = $request->get('type_id', 'all');
        $selectedCategoryId = $request->get('category_id', 'all');

        $selectedVehicleType = $selectedTypeId === 'all'
            ? 'All Types'
            : optional($vehicleTypes->firstWhere('id', $selectedTypeId))->type_name;

        $vehicleCategories = VehicleCategory::query()
            ->when($selectedTypeId !== 'all', function ($query) use ($selectedTypeId) {
                $query->whereHas('vehicles', function ($vehicleQuery) use ($selectedTypeId) {
                    $vehicleQuery->where('vehicle_type_id', $selectedTypeId);
                });
            })
            ->orderBy('name')
            ->get();

        $selectedVehicleCategory = $selectedCategoryId === 'all'
            ? 'All Categories'
            : optional($vehicleCategories->firstWhere('id', $selectedCategoryId))->name;

        $calendar = $this->buildCalendarData(
            $startOfMonth,
            $endOfMonth,
            $totalDays,
            $selectedTypeId,
            $selectedCategoryId
        );

        return view('vehicle-booking-calendar.index', [
            'calendar' => $calendar,
            'days' => $days,
            'month' => $month,
            'totalDays' => $totalDays,
            'selectedMonth' => $month->format('Y-m'),
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'vehicleTypes' => $vehicleTypes,
            'vehicleCategories' => $vehicleCategories,
            'selectedTypeId' => $selectedTypeId,
            'selectedCategoryId' => $selectedCategoryId,
            'selectedVehicleType' => $selectedVehicleType,
            'selectedVehicleCategory' => $selectedVehicleCategory,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $monthInput = $request->get('month', now()->format('Y-m'));
        $selectedTypeId = $request->get('type_id', 'all');
        $selectedCategoryId = $request->get('category_id', 'all');

        $month = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        $totalDays = $month->daysInMonth;

        $calendar = $this->buildCalendarData(
            $startOfMonth,
            $endOfMonth,
            $totalDays,
            $selectedTypeId,
            $selectedCategoryId
        );

        $vehicleTypeName = 'All Types';
        if ($selectedTypeId !== 'all') {
            $vehicleTypeName = optional(VehicleType::find($selectedTypeId))->type_name ?? 'Unknown Type';
        }

        $vehicleCategoryName = 'All Categories';
        if ($selectedCategoryId !== 'all') {
            $vehicleCategoryName = optional(VehicleCategory::find($selectedCategoryId))->name ?? 'Unknown Category';
        }

        $fileName = 'vehicle_booking_calendar_' . $month->format('Y_m') . '.csv';

        return response()->streamDownload(function () use (
            $calendar,
            $month,
            $totalDays,
            $vehicleTypeName,
            $vehicleCategoryName
        ) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['Vehicle Booking Calendar Report']);
            fputcsv($handle, ['Month', $month->format('F Y')]);
            fputcsv($handle, ['Vehicle Type', $vehicleTypeName]);
            fputcsv($handle, ['Vehicle Category', $vehicleCategoryName]);
            fputcsv($handle, ['Total Days In Month', $totalDays]);
            fputcsv($handle, []);

            fputcsv($handle, [
                'Vehicle No',
                'Vehicle Type',
                'Vehicle Category',
                'Total Bookings',
                'Used Days',
                'Usage %',
                'Utilization Status',
            ]);

            foreach ($calendar as $row) {
                $usage = (float) $row['usagePercent'];

                fputcsv($handle, [
                    $row['vehicle']->reg_no ?? ('Vehicle #' . $row['vehicle']->id),
                    $row['vehicle']->vehicleType->type_name ?? '-',
                    $row['vehicle']->vehicleCategory->name ?? '-',
                    count($row['ranges']),
                    $row['usedDays'],
                    $usage . '%',
                    $this->getUtilizationStatus($usage),
                ]);
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

    private function buildCalendarData(
        Carbon $startOfMonth,
        Carbon $endOfMonth,
        int $totalDays,
        string|int $typeId = 'all',
        string|int $categoryId = 'all'
    ) {
        $freezedVehicleIds = DB::table('vehicle_freezes')
            ->pluck('vehicle_id')
            ->toArray();

        $vehiclesQuery = Vehicle::with(['vehicleType', 'vehicleCategory'])
            ->where('status', '!=', 'disabled')
            ->orderBy('reg_no', 'asc');

        if ($typeId && $typeId !== 'all') {
            $vehiclesQuery->where('vehicle_type_id', $typeId);
        }

        if ($categoryId && $categoryId !== 'all') {
            $vehiclesQuery->where('vehicle_category_id', $categoryId);
        }

        $vehicles = $vehiclesQuery->get();

        $rentals = Rental::with('company')
            ->whereNull('deleted_at')
            ->whereNotNull('vehicle_id')
            ->whereNotNull('arrival_date')
            ->whereNotNull('departure_date')
            ->where(function ($query) use ($startOfMonth, $endOfMonth) {
                $query->whereDate('arrival_date', '<=', $endOfMonth)
                    ->whereDate('departure_date', '>=', $startOfMonth);
            })
            ->get()
            ->groupBy('vehicle_id');

        return $vehicles->map(function ($vehicle) use (
            $rentals,
            $startOfMonth,
            $endOfMonth,
            $totalDays,
            $freezedVehicleIds
        ) {
            $vehicleRentals = $rentals->get($vehicle->id, collect());

            $ranges = [];
            $usedDates = [];

            foreach ($vehicleRentals as $rental) {
                $arrival = Carbon::parse($rental->arrival_date)->startOfDay();
                $departure = Carbon::parse($rental->departure_date)->startOfDay();

                $rangeStart = $arrival->greaterThan($startOfMonth) ? $arrival : $startOfMonth;
                $rangeEnd = $departure->lessThan($endOfMonth) ? $departure : $endOfMonth;

                foreach (CarbonPeriod::create($rangeStart, $rangeEnd) as $date) {
                    $usedDates[$date->format('Y-m-d')] = true;
                }

                $ranges[] = [
                    'start_day' => $rangeStart->day,
                    'end_day' => $rangeEnd->day,
                ];
            }

            $usedDays = count($usedDates);

            if (in_array($vehicle->id, $freezedVehicleIds)) {
                $displayStatus = 'freezed';
            } elseif ($vehicle->status === 'active') {
                $displayStatus = 'active';
            } else {
                $displayStatus = 'disabled';
            }

            return [
                'vehicle' => $vehicle,
                'ranges' => $ranges,
                'usedDays' => $usedDays,
                'usagePercent' => $totalDays > 0
                    ? round(($usedDays / $totalDays) * 100, 2)
                    : 0,
                'displayStatus' => $displayStatus,
            ];
        });
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