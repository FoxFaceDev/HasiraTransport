<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        $query = Driver::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('license_number', 'like', "%{$search}%");
        }

        $drivers = $query->get();
        return view('drivers.index', compact('drivers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'license_number' => 'required|string|max:255',
            'has_certificate' => 'boolean',
            'certificate_number' => 'nullable|string|max:255|required_if:has_certificate,1',
        ]);

        Driver::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'license_number' => $validated['license_number'],
            'has_certificate' => $request->has('has_certificate'),
            'certificate_number' => $request->has('has_certificate') ? $request->certificate_number : null,
        ]);

        return back()->with('success', 'شۆفێرەکە بە سەرکەوتوویی زیادکرا.');
    }

    public function update(Request $request, Driver $driver)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'license_number' => 'required|string|max:255',
            'has_certificate' => 'boolean',
            'certificate_number' => 'nullable|string|max:255|required_if:has_certificate,1',
        ]);

        $driver->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'license_number' => $validated['license_number'],
            'has_certificate' => $request->has('has_certificate'),
            'certificate_number' => $request->has('has_certificate') ? $request->certificate_number : null,
        ]);

        return back()->with('success', 'زانیارییەکانی شۆفێر نوێکرایەوە.');
    }

    public function destroy(Driver $driver)
    {
        $driver->delete();
        return back()->with('success', 'شۆفێرەکە سڕایەوە.');
    }
}
