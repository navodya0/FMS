<?php

use App\Http\Controllers\TransportServiceController;
use App\Http\Controllers\Api\VehicleLookupController;
use App\Http\Controllers\QRDetailsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Models\Rental;

Route::middleware('api_token')->get('/vehicles/by-reg-no', [VehicleLookupController::class, 'byRegNo']);

Route::post('/transport-services/validate-vehicle', [
    TransportServiceController::class, 'validateVehicleForTransport'
]);

Route::get('/vehicle-details/{vehicleNumber}', [QRDetailsController::class, 'getVehicleDetails']);

Route::post('/rental-sync', function (Request $request) {
    $receivedSecret = $request->header('X-SYNC-SECRET');
    $expectedSecret = config('services.rental_sync.secret');

    if (($receivedSecret ?? '') !== ($expectedSecret ?? '')) {
        Log::warning('Unauthorized access attempt', [
            'received' => $receivedSecret,
            'expected' => $expectedSecret,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized',
        ], 401);
    }

    try {
        $data = $request->validate([
            'booking_number' => 'required|string',
            'vehicle_id' => 'required|integer',
            'company_id' => 'required|integer',
            'driver_name' => 'required|string',
            'arrival_date' => 'required|date',
            'departure_date' => 'required|date',
            'passengers' => 'required|integer',
            'status' => 'required|string',
            'created_by' => 'required|integer',

            // optional extras
            'transport_id' => 'nullable|integer',
            'vehicle_no' => 'nullable|string',
            'reason' => 'nullable|string',
            'contact_no' => 'nullable|string',
            'vehicle_type' => 'nullable|string',
        ]);

        $rental = Rental::create([
            'booking_number' => $data['booking_number'],
            'vehicle_id' => $data['vehicle_id'],
            'company_id' => $data['company_id'],
            'driver_name' => $data['driver_name'],
            'salutation' => null,
            'arrival_date' => $data['arrival_date'],
            'departure_date' => $data['departure_date'],
            'passengers' => $data['passengers'],
            'status' => $data['status'],
            'created_by' => $data['created_by'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rental created successfully',
            'data' => $rental,
        ], 201);

    } catch (Throwable $e) {
        Log::error('Rental Sync Error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
});