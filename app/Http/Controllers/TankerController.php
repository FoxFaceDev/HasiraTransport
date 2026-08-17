<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Setting;
use App\Models\Tanker;
use Illuminate\Http\Request;

class TankerController extends Controller
{
    public function index(Request $request)
    {
        $query = Tanker::with('driver');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('plate_number', 'like', "%{$search}%")
                  ->orWhere('sequence_number', 'like', "%{$search}%")
                  ->orWhere('sequence_owner', 'like', "%{$search}%")
                  ->orWhere('vin', 'like', "%{$search}%")
                  ->orWhere('truck_color', 'like', "%{$search}%")
                  ->orWhereHas('driver', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
        }

        $tankers = $query->get();
        $drivers = Driver::all();
        $maxTankers = Setting::where('key', 'max_tankers')->value('value') ?? 1039;
        
        return view('tankers.index', compact('tankers', 'drivers', 'maxTankers'));
    }

    public function store(Request $request)
    {
        $maxTankers = Setting::where('key', 'max_tankers')->value('value') ?? 1039;
        
        if (Tanker::count() >= $maxTankers) {
            return back()->withErrors(['limit' => 'Cannot add more tankers. Maximum limit reached.']);
        }

        $request->validate([
            'sequence_number' => 'required|string|max:255',
            'sequence_owner' => 'nullable|string|max:255',
            'plate_number' => 'required|string|unique:tankers,plate_number',
            'vin' => 'nullable|string|max:255|unique:tankers,vin',
            'truck_type' => 'required|string|max:255',
            'truck_color' => 'nullable|string|max:255',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        Tanker::create([
            'sequence_number' => $request->sequence_number,
            'sequence_owner' => $request->sequence_owner,
            'plate_number' => $request->plate_number,
            'vin' => $request->vin,
            'truck_type' => $request->truck_type,
            'truck_color' => $request->truck_color,
            'driver_id' => $request->driver_id,
        ]);

        return back()->with('success', 'بارهەڵگرەکە بە سەرکەوتوویی زیادکرا.');
    }

    public function update(Request $request, Tanker $tanker)
    {
        $request->validate([
            'sequence_number' => 'required|string|max:255',
            'sequence_owner' => 'nullable|string|max:255',
            'plate_number' => 'required|string|unique:tankers,plate_number,' . $tanker->id,
            'vin' => 'nullable|string|max:255|unique:tankers,vin,' . $tanker->id,
            'truck_type' => 'required|string|max:255',
            'truck_color' => 'nullable|string|max:255',
            'driver_id' => 'required|exists:drivers,id',
        ]);

        $tanker->update([
            'sequence_number' => $request->sequence_number,
            'sequence_owner' => $request->sequence_owner,
            'plate_number' => $request->plate_number,
            'vin' => $request->vin,
            'truck_type' => $request->truck_type,
            'truck_color' => $request->truck_color,
            'driver_id' => $request->driver_id,
        ]);

        return back()->with('success', 'زانیارییەکانی بارهەڵگر نوێکرایەوە.');
    }

    public function destroy(Tanker $tanker)
    {
        $tanker->delete();
        return back()->with('success', 'بارهەڵگرەکە سڕایەوە.');
    }

    public function updateSetting(Request $request)
    {
        $request->validate([
            'max_tankers' => 'required|integer|min:1',
        ]);

        Setting::updateOrCreate(
            ['key' => 'max_tankers'],
            ['value' => $request->max_tankers]
        );

        return back()->with('success', 'Limit updated.');
    }
}
