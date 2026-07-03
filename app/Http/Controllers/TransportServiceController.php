<?php

namespace App\Http\Controllers;

use App\Models\TransportService;
use App\Services\ErpApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Vehicle;
use Carbon\Carbon;
use App\Models\Rental;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Transfer;

class TransportServiceController extends Controller
{
    private function generateBookingNumber(string $type): string
    {
        $prefix = strtolower($type);

        $lastRental = Rental::where('booking_number', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->first();

        $nextNumber = 1;

        if (
            $lastRental &&
            preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $lastRental->booking_number, $matches)
        ) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%03d', $prefix, $nextNumber);
    }

    public function store(Request $request, ErpApi $erp)
    {
        $type = $request->input('type');
        $itemKey = $type === 'transfers' ? 'transfer_items' : 'shuttle_items';

        $data = $request->validate([
            'type' => 'required|in:shuttle,transfers',

            "{$itemKey}" => 'required|array|min:1',
            "{$itemKey}.*.selected" => 'nullable|in:1',
            "{$itemKey}.*.transport_service_id" => 'nullable|exists:transport_services,id',
            "{$itemKey}.*.rental_id" => 'nullable|exists:rentals,id',
            "{$itemKey}.*.transfer_id" => 'nullable|exists:transfers,id',
            "{$itemKey}.*.vehicle_id" => 'nullable|exists:vehicles,id',
            "{$itemKey}.*.vehicle_type_id" => 'nullable|exists:vehicle_types,id',
            "{$itemKey}.*.employee_id" => 'nullable|integer',
            "{$itemKey}.*.assigned_start_at" => 'nullable|date',
            "{$itemKey}.*.assigned_end_at" => 'nullable|date',
            "{$itemKey}.*.pickup_location" => 'nullable|string|max:255',
            "{$itemKey}.*.dropoff_location" => 'nullable|string|max:255',
            "{$itemKey}.*.passenger_count" => 'nullable|integer|min:1',
            "{$itemKey}.*.trip_code" => 'nullable|string|max:255',
            "{$itemKey}.*.note" => 'nullable|string',
            "{$itemKey}.*.is_vehicle_assigned" => 'nullable|boolean',
        ]);

        $items = collect($data[$itemKey])
            ->filter(fn ($item) => !empty($item['selected']))
            ->values();

        if ($items->isEmpty()) {
            return back()->withErrors([
                $itemKey => 'Please select at least one booking.'
            ])->withInput();
        }

        $chauffers = session('chauffers') ?? [];
        $savedCount = 0;
        $erpFailed = [];

        foreach ($items as $item) {
            Validator::make($item, [
                'rental_id' => 'nullable|exists:rentals,id',
                'transfer_id' => $type === 'transfers' ? 'required|exists:transfers,id' : 'nullable',
                'vehicle_id' => 'nullable|exists:vehicles,id',
                'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
                'employee_id' => 'required|integer',
                'assigned_start_at' => 'required|date',
                'assigned_end_at' => 'nullable|date|after_or_equal:assigned_start_at',
                'pickup_location' => 'required|string|max:255',
                'dropoff_location' => 'required|string|max:255',
                'passenger_count' => 'nullable|integer|min:1',
                'trip_code' => 'nullable|string|max:255',
                'note' => 'nullable|string',
                'is_vehicle_assigned' => 'nullable|boolean',
            ])->validate();

            try {
                $isVehicleAssigned = array_key_exists('is_vehicle_assigned', $item)
                    ? !empty($item['is_vehicle_assigned'])
                    : !empty($item['vehicle_id']);

                $vehicle = !empty($item['vehicle_id']) ? Vehicle::find($item['vehicle_id']) : null;

                $transportService = !empty($item['transport_service_id'])
                    ? TransportService::find($item['transport_service_id'])
                    : null;

                $transportPayload = [
                    'rental_id' => $item['rental_id'] ?? null,
                    'type' => $type,
                    'vehicle_id' => $isVehicleAssigned ? ($item['vehicle_id'] ?? null) : null,
                    'vehicle_type_id' => $isVehicleAssigned
                        ? ($item['vehicle_type_id'] ?? $vehicle?->vehicle_type_id)
                        : ($item['vehicle_type_id'] ?? null),
                    'employee_id' => $item['employee_id'],
                    'is_vehicle_assigned' => $isVehicleAssigned ? 1 : 0,
                    'assigned_start_at' => $item['assigned_start_at'],
                    'assigned_end_at' => $item['assigned_end_at'] ?? null,
                    'pickup_location' => $item['pickup_location'],
                    'dropoff_location' => $item['dropoff_location'],
                    'passenger_count' => $item['passenger_count'] ?? null,
                    'trip_code' => $item['trip_code'] ?? null,
                    'note' => $item['note'] ?? null,
                    'status' => 'ASSIGNED',
                ];

                if ($type === 'transfers') {
                    $transportPayload['transfer_id'] = $item['transfer_id'];
                }

                if ($transportService) {
                    $transportService->update($transportPayload);
                } else {
                    $transportService = TransportService::create($transportPayload);
                }

                if ($isVehicleAssigned && !empty($item['vehicle_id'])) {
                    $bookingType = $type === 'transfers' ? 'transfer' : $type;

                    $rental = Rental::create([
                        'booking_number' => $this->generateBookingNumber($bookingType),
                        'vehicle_id' => $item['vehicle_id'],
                        'company_id' => 1,
                        'driver_name' => $bookingType,
                        'salutation' => null,
                        'arrival_date' => $item['assigned_start_at'],
                        'departure_date' => $item['assigned_end_at'] ?? $item['assigned_start_at'],
                        'passengers' => $item['passenger_count'] ?? null,
                        'status' => 'booked',
                        'created_by' => Auth::id(),
                    ]);

                    if ((int) $transportService->rental_id !== (int) $rental->id) {
                        $transportService->update([
                            'rental_id' => $rental->id,
                        ]);
                    }
                }

                $savedCount++;

                $transportService->load(['vehicle', 'vehicleType']);

                $employee = collect($chauffers)->firstWhere('employee_id', $item['employee_id']);
                $erpType = $transportService->type;

                $payload = [
                    'source_id' => $transportService->id,
                    'type' => $erpType,
                    'vehicle_no' => $transportService->vehicle?->reg_no,
                    'vehicle_type' => $transportService->vehicleType?->type_name,
                    'is_vehicle_assigned' => (string) ($transportService->is_vehicle_assigned ? 1 : 0),
                    'employee_id' => $item['employee_id'],
                    'chauffer_phone' => $employee['whatsapp_number'] ?? null,
                    'chauffer_name' => $employee['preferred_name'] ?? null,
                    'assigned_start_at' => $transportService->assigned_start_at,
                    'assigned_end_at' => $transportService->assigned_end_at,
                    'pickup_location' => $transportService->pickup_location,
                    'dropoff_location' => $transportService->dropoff_location,
                    'passenger_count' => $transportService->passenger_count,
                    'note' => $transportService->note,
                    'status' => 'ASSIGNED',
                ];

                Log::info('ERP transport payload', [
                    'mode' => !empty($item['transport_service_id']) ? 'update' : 'create',
                    'transport_service_id' => $transportService->id,
                    'type' => $type,
                    'payload' => $payload,
                ]);

                try {
                    if (!empty($item['transport_service_id'])) {
                        $erp->updateTransport($transportService->id, $payload);
                    } else {
                        $erp->createTransport($payload);
                    }
                } catch (\Throwable $e) {
                    Log::error('ERP transport sync failed', [
                        'transport_service_id' => $transportService->id,
                        'type' => $type,
                        'message' => $e->getMessage(),
                        'payload' => $payload,
                    ]);

                    $erpFailed[] = $transportService->id;
                }
            } catch (\Throwable $e) {
                Log::error('Transport service store failed', [
                    'type' => $type,
                    'message' => $e->getMessage(),
                    'item' => $item,
                ]);

                continue;
            }
        }

        if (!empty($erpFailed)) {
            return back()->with('warning', "{$savedCount} {$type} transport service(s) saved, but ERP sync failed for some records.");
        }

        return back()->with('success', "{$savedCount} {$type} transport service(s) saved successfully.");
    }

    public function update(Request $request, TransportService $transportService, ErpApi $erp)
    {
        $data = $request->validate([
            'type' => 'required|in:shuttle,transfers,office,personal',
            'vehicle_id' => 'nullable|exists:vehicles,id|required_without:vehicle_type_id',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id|required_if:is_vehicle_assigned,1',
            'is_vehicle_assigned' => 'nullable|boolean',
            'employee_id' => 'required|integer',
            'assigned_start_at' => 'required|date',
            'assigned_end_at' => 'nullable|date|after_or_equal:assigned_start_at',
            'pickup_location' => 'nullable|string|max:255',
            'dropoff_location' => 'required|string|max:255',
            'passenger_count' => 'nullable|integer|min:1',
            'trip_code' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ]);

        $isVehicleAssigned = !empty($data['vehicle_id']);

        $transportService->update([
            'type' => $data['type'],
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'vehicle_type_id' => $data['vehicle_type_id'] ?? null,
            'employee_id' => $data['employee_id'],
            'is_vehicle_assigned' => $isVehicleAssigned,
            'assigned_start_at' => $data['assigned_start_at'],
            'assigned_end_at' => $data['assigned_end_at'] ?? null,
            'pickup_location' => $data['pickup_location'] ?? null,
            'dropoff_location' => $data['dropoff_location'],
            'passenger_count' => $data['passenger_count'],
            'trip_code' => $data['trip_code'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        $transportService->load(['vehicle', 'vehicleType']);

        $chauffers = session('chauffers') ?? [];
        $employee = collect($chauffers)->firstWhere('employee_id', $data['employee_id']);

        $payload = [
            'type' => $transportService->type,
            'vehicle_no' => $transportService->vehicle?->reg_no,
            'vehicle_type' => $transportService->vehicleType?->type_name,
            'is_vehicle_assigned' => $transportService->is_vehicle_assigned,
            'employee_id' => $data['employee_id'],
            'chauffer_phone' => $employee['whatsapp_number'] ?? null,
            'chauffer_name' => $employee['preferred_name'] ?? null,
            'assigned_start_at' => $transportService->assigned_start_at,
            'assigned_end_at' => $transportService->assigned_end_at,
            'pickup_location' => $transportService->pickup_location,
            'dropoff_location' => $transportService->dropoff_location,
            'passenger_count' => $transportService->passenger_count,
            'note' => $transportService->note,
        ];

        try {
            $erp->updateTransport($transportService->id, $payload);
        } catch (\Throwable $e) {
            Log::error('Admin-ERP sync failed (transport update)', [
                'source_id' => $transportService->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Updated in FMS but Admin-ERP sync failed.');
        }

        return back()->with('success', 'Transport service updated + synced to Admin-ERP.');
    }

    public function destroy(TransportService $transportService, Request $request, ErpApi $erp)
    {
        $validated = $request->validate([
            'delete_note' => 'required|string|min:3',
        ]);

        $sourceId = $transportService->id;

        $transportService->update([
            'delete_note' => $validated['delete_note'],
            'deleted_by' => Auth::id(),
        ]);

        $transportService->delete();

        try {
            $erp->deleteTransport($sourceId);
        } catch (\Throwable $e) {
            Log::error('Admin-ERP sync failed (transport delete)', [
                'source_id' => $sourceId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Deleted in FMS but Admin-ERP sync failed.');
        }

        return back()->with('success', 'Transport service deleted + synced to Admin-ERP.');
    }

    public function validateVehicleForTransport(Request $request)
    {
        $data = $request->validate([
            'vehicle_no' => 'required|string|max:50',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
            'vehicle_type_name' => 'nullable|string|max:100',
            'assigned_start_at' => 'nullable|date',
            'assigned_end_at' => 'nullable|date|after_or_equal:assigned_start_at',
            'transport_service_id' => 'nullable|integer|exists:transport_services,id',
        ]);

        $vehicle = Vehicle::with('vehicleType')
            ->where('reg_no', $data['vehicle_no'])
            ->first();

        if (!$vehicle) {
            return response()->json([
                'ok' => false,
                'message' => 'Vehicle not found.',
            ], 404);
        }

        $vehicleData = [
            'id' => $vehicle->id,
            'reg_no' => $vehicle->reg_no,
            'vehicle_type_id' => $vehicle->vehicle_type_id,
            'vehicle_type_name' => $vehicle->vehicleType?->type_name,
        ];

        $fail = fn($message, $status = 422) => response()->json([
            'ok' => false,
            'message' => $message,
            'vehicle' => $vehicleData,
        ], $status);

        if (empty($data['vehicle_type_id']) && !empty($data['vehicle_type_name'])) {
            $data['vehicle_type_id'] = \App\Models\VehicleType::where('type_name', $data['vehicle_type_name'])->value('id');
        }

        if (!empty($data['vehicle_type_id']) && (int) $vehicle->vehicle_type_id !== (int) $data['vehicle_type_id']) {
            return $fail('Selected vehicle does not match the required vehicle type.');
        }

        if ($vehicle->status === 'disabled') {
            return $fail('Vehicle is disabled.');
        }

        if (empty($data['assigned_start_at'])) {
            return response()->json([
                'ok' => true,
                'message' => 'Vehicle number and vehicle type match.',
                'vehicle' => $vehicleData,
            ]);
        }

        $start = Carbon::parse($data['assigned_start_at']);
        $end = Carbon::parse($data['assigned_end_at'] ?? $data['assigned_start_at']);

        if ($vehicle->freezes()
            ->where('start_date', '<=', $end)
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start))
            ->exists()) {
            return $fail('Vehicle is frozen for the selected period.');
        }

        if ($vehicle->rentals()
            ->whereDate('arrival_date', '<=', $end)
            ->whereDate('departure_date', '>=', $start)
            ->exists()) {
            return $fail('Vehicle is not available for the selected period due to an existing rental.');
        }

        if ($vehicle->transportServices()
            ->when(!empty($data['transport_service_id']), fn($q) => $q->where('id', '!=', $data['transport_service_id']))
            ->where('assigned_start_at', '<=', $end)
            ->where(fn($q) => $q->whereNull('assigned_end_at')->orWhere('assigned_end_at', '>=', $start))
            ->exists()) {
            return $fail('Vehicle is already assigned to another transport service in the selected period.');
        }

        return response()->json([
            'ok' => true,
            'message' => 'Vehicle is valid and available.',
            'vehicle' => $vehicleData,
        ]);
    }

    public function getShuttleBookings(Request $request)
    {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($request->date)->toDateString();

        $rentals = Rental::query()
            ->where(fn ($q) => $q
                ->whereDate('arrival_date', $date)
                ->orWhereDate('departure_date', $date))
            ->where(fn ($q) => $q
                ->whereNull('driver_name')
                ->orWhereNotIn('driver_name', ['shuttle', 'transfer', 'transfers']))
            ->where('booking_number', 'not like', 'shuttle-%')
            ->where('booking_number', 'not like', 'transfer-%')
            ->where('booking_number', 'not like', 'transfers-%')
            ->orderBy('arrival_date')
            ->orderBy('departure_date')
            ->get([
                'id',
                'booking_number',
                'driver_name',
                'salutation',
                'arrival_date',
                'departure_date',
                'passengers',
                'notes',
                'vehicle_pickup',
                'emer_customer_name',
                'emer_no_of_passengers',
            ]);

        $buildRow = function ($r, string $tripType, ?TransportService $existing) {
            return [
                'id' => $r->id,
                'booking_number' => $r->booking_number,
                'customer_name' => $r->emer_customer_name ?: $r->driver_name,
                'salutation' => $r->salutation,
                'arrival_date' => optional($r->arrival_date)->format('Y-m-d H:i:s'),
                'departure_date' => optional($r->departure_date)->format('Y-m-d H:i:s'),
                'passengers' => $r->emer_no_of_passengers ?: $r->passengers,
                'notes' => $r->notes,
                'vehicle_pickup' => $r->vehicle_pickup,
                'trip_type' => $tripType,

                'existing_transport_id' => $existing?->id,
                'existing_vehicle_id' => $existing?->vehicle_id,
                'existing_employee_id' => $existing?->employee_id,
                'existing_assigned_start_at' => $existing?->assigned_start_at
                    ? Carbon::parse($existing->assigned_start_at)->format('Y-m-d H:i:s')
                    : null,
                'existing_assigned_end_at' => $existing?->assigned_end_at
                    ? Carbon::parse($existing->assigned_end_at)->format('Y-m-d H:i:s')
                    : null,
                'existing_pickup_location' => $existing?->pickup_location,
                'existing_dropoff_location' => $existing?->dropoff_location,
                'existing_passenger_count' => $existing?->passenger_count,
                'existing_note' => $existing?->note,
            ];
        };

        $getExisting = fn ($rentalId, $tripType) => TransportService::query()
            ->where('type', 'shuttle')
            ->where('rental_id', $rentalId)
            ->where('trip_code', $tripType)
            ->latest('id')
            ->first();

        $data = $rentals->flatMap(function ($r) use ($date, $buildRow, $getExisting) {
            $rows = [];

            if ($r->arrival_date && Carbon::parse($r->arrival_date)->toDateString() === $date) {
                $rows[] = $buildRow($r, 'arrival', $getExisting($r->id, 'arrival'));
            }

            if ($r->departure_date && Carbon::parse($r->departure_date)->toDateString() === $date) {
                $rows[] = $buildRow($r, 'departure', $getExisting($r->id, 'departure'));
            }

            return $rows;
        })->values();

        return response()->json($data);
    }

    public function getAvailableVehicles(Request $request)
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->endOfDay();

        $vehicles = Vehicle::query()
            ->with(['company', 'vehicleType'])
            ->where('status', '!=', 'disabled')

            // Exclude frozen vehicles in selected date range
            ->whereDoesntHave('freezes', function ($q) use ($start, $end) {
                $q->whereDate('start_date', '<=', $end)
                ->where(function ($sub) use ($start) {
                    $sub->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $start);
                });
            })

            ->whereDoesntHave('rentals', function ($q) use ($start, $end) {
                $q->where('status', '!=', 'arrived')
                ->whereDate('arrival_date', '<=', $end)
                ->whereDate('departure_date', '>=', $start);
            })

            ->orderBy('reg_no')
            ->get()
            ->map(function ($vehicle) {
                return [
                    'id' => $vehicle->id,
                    'reg_no' => $vehicle->reg_no,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'company_id' => $vehicle->company_id,
                    'company_name' => $vehicle->company->name ?? null,
                    'vehicle_type_id' => $vehicle->vehicle_type_id,
                    'vehicle_type_name' => $vehicle->vehicleType->type_name ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Available vehicles fetched successfully.',
            'data' => $vehicles,
        ]);
    }
}