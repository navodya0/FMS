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
        $data = $request->validate([
            'type' => 'required|in:shuttle,transfers,office,personal',
            'vehicle_id' => 'nullable|exists:vehicles,id|required_without:vehicle_type_id',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id|required_without:vehicle_id',
            'is_vehicle_assigned' => 'nullable|boolean',
            'employee_id' => 'required|integer',
            'assigned_start_at' => 'required|date',
            'assigned_end_at' => 'nullable|date|after_or_equal:assigned_start_at',
            'pickup_location' => 'nullable|string|max:255',
            'dropoff_location' => 'required|string|max:255',
            'passenger_count' => 'required|integer|min:1',
            'trip_code' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ]);

        $isVehicleAssigned = !empty($data['vehicle_id']);
        $vehicleTypeId = $data['vehicle_type_id'] ?? null;

        if ($isVehicleAssigned) {
            $vehicle = Vehicle::find($data['vehicle_id']);
            $vehicleTypeId = $vehicle?->vehicle_type_id;
        }

        $ts = TransportService::create([
            'type' => $data['type'],
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'vehicle_type_id' => $vehicleTypeId,
            'is_vehicle_assigned' => $isVehicleAssigned ? '1' : '0',
            'assigned_start_at' => $data['assigned_start_at'],
            'assigned_end_at' => $data['assigned_end_at'] ?? null,
            'pickup_location' => $data['pickup_location'] ?? null,
            'dropoff_location' => $data['dropoff_location'],
            'passenger_count' => $data['passenger_count'],
            'trip_code' => $data['trip_code'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        if (!empty($data['vehicle_id'])) {
            Rental::create([
                'booking_number' => $this->generateBookingNumber($data['type']),
                'vehicle_id' => $data['vehicle_id'],
                'company_id' => 1,
                'driver_name' => $data['type'],
                'salutation' => null,
                'arrival_date' => $data['assigned_start_at'],
                'departure_date' => $data['assigned_end_at'] ?? $data['assigned_start_at'],
                'passengers' => $data['passenger_count'],
                'status' => 'booked',
                'created_by' => Auth::id(),
            ]);
        }

        $ts->load(['vehicle', 'vehicleType']);

        $chauffers = session('chauffers') ?? [];
        $employee = collect($chauffers)->firstWhere('employee_id', $data['employee_id']);

        $payload = [
            'source_id' => $ts->id,
            'type' => $ts->type,
            'vehicle_no' => $ts->vehicle?->reg_no,
            'vehicle_type' => $ts->vehicleType?->type_name,
            'is_vehicle_assigned' => $ts->is_vehicle_assigned ? '1' : '0',
            'employee_id' => $data['employee_id'],
            'chauffer_phone' => $employee['whatsapp_number'] ?? null,
            'chauffer_name' => $employee['preferred_name'] ?? null,
            'assigned_start_at' => $ts->assigned_start_at,
            'assigned_end_at' => $ts->assigned_end_at,
            'pickup_location' => $ts->pickup_location,
            'dropoff_location' => $ts->dropoff_location,
            'passenger_count' => $ts->passenger_count,
            'note' => $ts->note,
            'status' => 'ASSIGNED',
        ];

        try {
            $erp->createTransport($payload);
        } catch (\Throwable $e) {
            Log::error('Admin-ERP sync failed (transport store)', [
                'source_id' => $ts->id,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Saved in FMS but Admin-ERP sync failed.');
        }

        return back()->with('success', 'Transport service added + synced to Admin-ERP.');
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
            'passenger_count' => 'required|integer|min:1',
            'trip_code' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ]);

        $isVehicleAssigned = !empty($data['vehicle_id']);

        $transportService->update([
            'type' => $data['type'],
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'vehicle_type_id' => $data['vehicle_type_id'] ?? null,
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

        // If vehicle_type_id is missing but vehicle_type_name is given, resolve it
        if (empty($data['vehicle_type_id']) && !empty($data['vehicle_type_name'])) {
            $data['vehicle_type_id'] = \App\Models\VehicleType::where('type_name', $data['vehicle_type_name'])->value('id');
        }

        // Check vehicle no + vehicle type match
        if (!empty($data['vehicle_type_id']) && (int) $vehicle->vehicle_type_id !== (int) $data['vehicle_type_id']) {
            return $fail('Selected vehicle does not match the required vehicle type.');
        }

        if ($vehicle->status === 'disabled') {
            return $fail('Vehicle is disabled.');
        }

        // If dates are not provided, only validate vehicle/type match and return success
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
}