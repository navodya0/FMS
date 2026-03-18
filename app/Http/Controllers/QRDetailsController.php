<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class QRDetailsController extends Controller
{
    public function index()
    {
        $vehicles = Vehicle::where('status', 'active')
            ->with('qrImages', 'company','ownershipType')
            ->get();

        $vehicleCount = DB::table('vehicle_qr_images')
            ->distinct('vehicle_id')
            ->count('vehicle_id');

        return view('qr-details.index', compact('vehicles', 'vehicleCount'));
    }

    public function upload(Request $request, $id)
    {
        $request->validate([
            'qr_image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $vehicle = Vehicle::findOrFail($id);

        $file = $request->file('qr_image');
        $fileName = $file->getClientOriginalName();
        $folderPath = 'vehicle-qa/' . $vehicle->reg_no;
        $path = $file->storeAs($folderPath, $fileName, 'public');

        DB::table('vehicle_qr_images')->insert([
            'vehicle_id' => $vehicle->id,
            'qr_image' => $path,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return back()->with('success', 'QR image uploaded successfully');
    }

    public function getVehicleDetails($vehicleNumber)
    {
        $vehicle = Vehicle::with(['company', 'qrImages'])
            ->where('reg_no', $vehicleNumber)
            ->where('status', 'active')
            ->first();

        if (!$vehicle) {
            return response()->json([
                'status' => false,
                'message' => 'Vehicle not found'
            ], 404);
        }

        $latestQrImage = $vehicle->qrImages->last();

        return response()->json([
            'status' => true,
            'message' => 'Vehicle details fetched successfully',
            'data' => [
                'vehicle_number' => $vehicle->reg_no,
                'company_name' => $vehicle->company->name ?? null,
                'image' => $latestQrImage
                    ? url('storage/app/public/' . str_replace('%2F', '/', rawurlencode($latestQrImage->qr_image)))
                    : null,
            ]
        ]);
    }
}