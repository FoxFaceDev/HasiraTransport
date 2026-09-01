<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Tanker;
use Illuminate\Http\Request;

class TankerController extends Controller
{
    public function index(Request $request)
    {
        $query = Tanker::query()->orderBy('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('plate_number', 'like', "%{$search}%")
                    ->orWhere('sequence_number', 'like', "%{$search}%")
                    ->orWhere('sequence_owner', 'like', "%{$search}%")
                    ->orWhere('sequence_owner_phone', 'like', "%{$search}%")
                    ->orWhere('vin', 'like', "%{$search}%")
                    ->orWhere('truck_type', 'like', "%{$search}%")
                    ->orWhere('truck_color', 'like', "%{$search}%");
            });
        }

        $tankers = $query->get();
        $tankerCount = Tanker::query()->count();
        $maxTankers = Setting::where('key', 'max_tankers')->value('value') ?? 1039;

        return view('tankers.index', compact('tankers', 'tankerCount', 'maxTankers'));
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
            'sequence_owner_phone' => 'nullable|string|max:255',
            'plate_number' => 'required|string|unique:tankers,plate_number',
            'vin' => 'nullable|string|max:255',
            'truck_type' => 'required|string|max:255',
            'truck_color' => 'nullable|string|max:255',
        ]);

        Tanker::create([
            'sequence_number' => $request->sequence_number,
            'sequence_owner' => $request->sequence_owner,
            'sequence_owner_phone' => $request->sequence_owner_phone,
            'plate_number' => $request->plate_number,
            'vin' => $request->vin,
            'truck_type' => $request->truck_type,
            'truck_color' => $request->truck_color,
        ]);

        return back()->with('success', 'بارهەڵگرەکە بە سەرکەوتوویی زیادکرا.');
    }

    public function update(Request $request, Tanker $tanker)
    {
        $request->validate([
            'sequence_number' => 'required|string|max:255',
            'sequence_owner' => 'nullable|string|max:255',
            'sequence_owner_phone' => 'nullable|string|max:255',
            'plate_number' => 'required|string|unique:tankers,plate_number,'.$tanker->id,
            'vin' => 'nullable|string|max:255',
            'truck_type' => 'required|string|max:255',
            'truck_color' => 'nullable|string|max:255',
        ]);

        $tanker->update([
            'sequence_number' => $request->sequence_number,
            'sequence_owner' => $request->sequence_owner,
            'sequence_owner_phone' => $request->sequence_owner_phone,
            'plate_number' => $request->plate_number,
            'vin' => $request->vin,
            'truck_type' => $request->truck_type,
            'truck_color' => $request->truck_color,
        ]);

        return back()->with('success', 'زانیارییەکانی بارهەڵگر نوێکرایەوە.');
    }

    public function destroy(Tanker $tanker)
    {
        $tanker->delete();

        return back()->with('success', 'بارهەڵگرەکە سڕایەوە.');
    }

    public function block(Tanker $tanker)
    {
        $tanker->update(['blocked_at' => now()]);

        return back()->with('success', 'خەتەکە بلۆک کرا.');
    }

    public function unblock(Tanker $tanker)
    {
        $tanker->update(['blocked_at' => null]);

        return back()->with('success', 'بلۆکی خەتەکە لابرا.');
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
