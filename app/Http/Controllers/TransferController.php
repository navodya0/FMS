<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'transfers' => 'required|array|min:1',
            'transfers.*.booking_number' => 'required|string|max:50|distinct|unique:transfers,booking_number',
            'transfers.*.start_date' => 'required|date',
            'transfers.*.end_date' => 'nullable|date|after_or_equal:transfers.*.start_date',
        ]);

        foreach ($data['transfers'] as $transferData) {
            Transfer::create([
                'booking_number' => $transferData['booking_number'],
                'start_date' => $transferData['start_date'],
                'end_date' => $transferData['end_date'] ?? null,
            ]);
        }

        return back()->with('success', count($data['transfers']) . ' transfer booking(s) added successfully.');
    }
}