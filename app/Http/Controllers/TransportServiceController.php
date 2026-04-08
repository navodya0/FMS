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

class TransportServiceController extends Controller
{
    private function generateBookingNumber(string $type): string
    {
        $prefix = strtolower($type);

        $lastRental = Rental::where('booking_number', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->first();

        $nextNumber = 1;

        if ($lastRental && preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $lastRental->booking_number, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%03d', $prefix, $nextNumber);
    }

    public function store(Request $request, ErpApi $erp)
    {
        Log::info('Shuttle bulk store request received', [
            'user_id' => Auth::id(),
            'payload' => $request->all(),
        ]);

        $data = $request->validate([
            'type' => 'required|in:shuttle',
            'shuttle_items' => 'required|array|min:1',
            'shuttle_items.*.selected' => 'nullable|in:1',
            'shuttle_items.*.transport_service_id' => 'nullable|exists:transport_services,id',
            'shuttle_items.*.rental_id' => 'nullable|exists:rentals,id',
            'shuttle_items.*.vehicle_id' => 'nullable|exists:vehicles,id',
            'shuttle_items.*.employee_id' => 'nullable|integer',
            'shuttle_items.*.assigned_start_at' => 'nullable|date',
            'shuttle_items.*.assigned_end_at' => 'nullable|date',
            'shuttle_items.*.pickup_location' => 'nullable|string|max:255',
            'shuttle_items.*.dropoff_location' => 'nullable|string|max:255',
            'shuttle_items.*.passenger_count' => 'nullable|integer|min:1',
            'shuttle_items.*.trip_code' => 'nullable|string|max:255',
            'shuttle_items.*.note' => 'nullable|string',
        ]);

        $items = collect($data['shuttle_items'])
            ->filter(fn ($item) => !empty($item['selected']))
            ->values();

        if ($items->isEmpty()) {
            return back()->withErrors([
                'shuttle_items' => 'Please select at least one booking.'
            ])->withInput();
        }

        $chauffers = session('chauffers') ?? [];
        $savedCount = 0;
        $erpFailed = [];

        foreach ($items as $index => $item) {
            Validator::make($item, [
                'rental_id' => 'nullable|exists:rentals,id',
                'vehicle_id' => 'required|exists:vehicles,id',
                'employee_id' => 'required|integer',
                'assigned_start_at' => 'required|date',
                'assigned_end_at' => 'nullable|date|after_or_equal:assigned_start_at',
                'pickup_location' => 'required|string|max:255',
                'dropoff_location' => 'required|string|max:255',
                'passenger_count' => 'nullable|integer|min:1',
                'trip_code' => 'nullable|string|max:255',
                'note' => 'nullable|string',
            ])->validate();

            try {
                Log::info('Processing shuttle item', [
                    'index' => $index,
                    'item' => $item,
                ]);

                $vehicle = Vehicle::find($item['vehicle_id']);

                $transportService = null;

                if (!empty($item['transport_service_id'])) {
                    $transportService = TransportService::find($item['transport_service_id']);
                }

                if ($transportService) {
                    $transportService->update([
                        'rental_id' => $item['rental_id'] ?? null,
                        'type' => 'shuttle',
                        'vehicle_id' => $item['vehicle_id'],
                        'vehicle_type_id' => $vehicle?->vehicle_type_id,
                        'employee_id' => $item['employee_id'],
                        'is_vehicle_assigned' => 1,
                        'assigned_start_at' => $item['assigned_start_at'],
                        'assigned_end_at' => $item['assigned_end_at'] ?? null,
                        'pickup_location' => $item['pickup_location'],
                        'dropoff_location' => $item['dropoff_location'],
                        'passenger_count' => $item['passenger_count'] ?? null,
                        'trip_code' => $item['trip_code'] ?? null,
                        'note' => $item['note'] ?? null,
                    ]);

                    Log::info('TransportService updated', [
                        'transport_service_id' => $transportService->id,
                    ]);
                } else {
                    $transportService = TransportService::create([
                        'rental_id' => $item['rental_id'] ?? null,
                        'type' => 'shuttle',
                        'vehicle_id' => $item['vehicle_id'],
                        'vehicle_type_id' => $vehicle?->vehicle_type_id,
                        'employee_id' => $item['employee_id'],
                        'is_vehicle_assigned' => 1,
                        'assigned_start_at' => $item['assigned_start_at'],
                        'assigned_end_at' => $item['assigned_end_at'] ?? null,
                        'pickup_location' => $item['pickup_location'],
                        'dropoff_location' => $item['dropoff_location'],
                        'passenger_count' => $item['passenger_count'] ?? null,
                        'trip_code' => $item['trip_code'] ?? null,
                        'note' => $item['note'] ?? null,
                    ]);

                    Log::info('TransportService created', [
                        'transport_service_id' => $transportService->id,
                    ]);
                }

                if (!empty($item['vehicle_id'])) {
                    $rental = Rental::create([
                        'booking_number' => $this->generateBookingNumber($data['type']),
                        'vehicle_id' => $item['vehicle_id'],
                        'company_id' => 1,
                        'driver_name' => $data['type'],
                        'salutation' => null,
                        'arrival_date' => $item['assigned_start_at'],
                        'departure_date' => $item['assigned_end_at'] ?? $item['assigned_start_at'],
                        'passengers' => $item['passenger_count'] ?? null,
                        'status' => 'booked',
                        'created_by' => Auth::id(),
                    ]);

                    Log::info('Rental created', [
                        'rental_id' => $rental->id,
                        'transport_service_id' => $transportService->id,
                    ]);
                }

                $savedCount++;

                $transportService->load(['vehicle', 'vehicleType']);

                $employee = collect($chauffers)->firstWhere('employee_id', $item['employee_id']);

                $payload = [
                    'source_id' => $transportService->id,
                    'type' => $transportService->type,
                    'vehicle_no' => $transportService->vehicle?->reg_no,
                    'vehicle_type' => $transportService->vehicleType?->type_name,
                    'is_vehicle_assigned' => '1',
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

                try {
                    if (!empty($item['transport_service_id'])) {
                        $erp->updateTransport($transportService->id, $payload);
                    } else {
                        $erp->createTransport($payload);
                    }

                    Log::info('ERP sync success', [
                        'transport_service_id' => $transportService->id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('ERP sync failed', [
                        'transport_service_id' => $transportService->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $erpFailed[] = $transportService->id;
                }
            } catch (\Throwable $e) {
                Log::error('Failed processing shuttle item', [
                    'index' => $index,
                    'item' => $item,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        Log::info('Shuttle bulk store completed', [
            'saved' => $savedCount,
            'erp_failed' => $erpFailed,
        ]);

        if (!empty($erpFailed)) {
            return back()->with('warning', "{$savedCount} shuttle transport service(s) saved, but ERP sync failed for some records.");
        }

        return back()->with('success', "{$savedCount} shuttle transport service(s) saved successfully.");
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
            ->where(function ($q) use ($date) {
                $q->whereDate('arrival_date', $date)
                  ->orWhereDate('departure_date', $date);
            })
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

        $data = $rentals->flatMap(function ($r) use ($date) {
            $rows = [];

            $customerName = $r->emer_customer_name ?: $r->driver_name;
            $passengers = $r->emer_no_of_passengers ?: $r->passengers;

            if ($r->arrival_date && Carbon::parse($r->arrival_date)->toDateString() === $date) {
                $existingArrival = TransportService::query()
                    ->where('type', 'shuttle')
                    ->where('rental_id', $r->id)
                    ->where('trip_code', 'arrival')
                    ->latest('id')
                    ->first();

                $rows[] = [
                    'id' => $r->id,
                    'booking_number' => $r->booking_number,
                    'customer_name' => $customerName,
                    'salutation' => $r->salutation,
                    'arrival_date' => optional($r->arrival_date)->format('Y-m-d H:i:s'),
                    'departure_date' => optional($r->departure_date)->format('Y-m-d H:i:s'),
                    'passengers' => $passengers,
                    'notes' => $r->notes,
                    'vehicle_pickup' => $r->vehicle_pickup,
                    'trip_type' => 'arrival',

                    'existing_transport_id' => $existingArrival?->id,
                    'existing_vehicle_id' => $existingArrival?->vehicle_id,
                    'existing_employee_id' => $existingArrival?->employee_id,
                    'existing_assigned_start_at' => $existingArrival?->assigned_start_at ? Carbon::parse($existingArrival->assigned_start_at)->format('Y-m-d H:i:s') : null,
                    'existing_assigned_end_at' => $existingArrival?->assigned_end_at ? Carbon::parse($existingArrival->assigned_end_at)->format('Y-m-d H:i:s') : null,
                    'existing_pickup_location' => $existingArrival?->pickup_location,
                    'existing_dropoff_location' => $existingArrival?->dropoff_location,
                    'existing_passenger_count' => $existingArrival?->passenger_count,
                    'existing_note' => $existingArrival?->note,
                ];
            }

            if ($r->departure_date && Carbon::parse($r->departure_date)->toDateString() === $date) {
                $existingDeparture = TransportService::query()
                    ->where('type', 'shuttle')
                    ->where('rental_id', $r->id)
                    ->where('trip_code', 'departure')
                    ->latest('id')
                    ->first();

                $rows[] = [
                    'id' => $r->id,
                    'booking_number' => $r->booking_number,
                    'customer_name' => $customerName,
                    'salutation' => $r->salutation,
                    'arrival_date' => optional($r->arrival_date)->format('Y-m-d H:i:s'),
                    'departure_date' => optional($r->departure_date)->format('Y-m-d H:i:s'),
                    'passengers' => $passengers,
                    'notes' => $r->notes,
                    'vehicle_pickup' => $r->vehicle_pickup,
                    'trip_type' => 'departure',

                    'existing_transport_id' => $existingDeparture?->id,
                    'existing_vehicle_id' => $existingDeparture?->vehicle_id,
                    'existing_employee_id' => $existingDeparture?->employee_id,
                    'existing_assigned_start_at' => $existingDeparture?->assigned_start_at ? Carbon::parse($existingDeparture->assigned_start_at)->format('Y-m-d H:i:s') : null,
                    'existing_assigned_end_at' => $existingDeparture?->assigned_end_at ? Carbon::parse($existingDeparture->assigned_end_at)->format('Y-m-d H:i:s') : null,
                    'existing_pickup_location' => $existingDeparture?->pickup_location,
                    'existing_dropoff_location' => $existingDeparture?->dropoff_location,
                    'existing_passenger_count' => $existingDeparture?->passenger_count,
                    'existing_note' => $existingDeparture?->note,
                ];
            }

            return $rows;
        })->values();

        return response()->json($data);
    }
}