<?php

namespace App\Http\Controllers;

use App\Models\Barrel;
use Illuminate\Http\Request;

class BarrelController extends Controller
{
    public function index()
    {
        $barrels = Barrel::orderBy('barrel_number')->get();

        return view('fuel-logs.index', compact('barrels'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'capacity' => 'required|in:20,25',
        ]);

        $numbers = Barrel::pluck('barrel_number')->toArray();
        sort($numbers);

        $nextNumber = 1;
        foreach ($numbers as $number) {
            if ($number == $nextNumber) {
                $nextNumber++;
            } else {
                break;
            }
        }

        Barrel::create([
            'barrel_number' => $nextNumber,
            'capacity' => $request->capacity,
            'status' => 'Available',
        ]);

        return redirect()->route('fuel-logs.index')->with('success', 'Barrel added successfully.');
    }

    public function destroy($id)
    {
        $barrel = Barrel::findOrFail($id);
        $barrel->delete();

        return redirect()->route('fuel-logs.index')->with('success', 'Barrel removed successfully.');
    }
}