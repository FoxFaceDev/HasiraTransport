<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Support\XlsxWriter;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        $query = Driver::query()->orderBy('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('license_number', 'like', "%{$search}%")
                    ->orWhere('certificate_number', 'like', "%{$search}%");
            });
        }

        $drivers = $query->get();

        return view('drivers.index', compact('drivers'));
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $query = Driver::query()->orderBy('id');

        if ($search = trim($validated['search'] ?? '')) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('license_number', 'like', "%{$search}%")
                    ->orWhere('certificate_number', 'like', "%{$search}%");
            });
        }

        $rows = [[
            '#',
            'ناوی شۆفێر',
            'مۆبایل',
            'مۆڵەتی شۆفێری',
            'شەهادە',
        ]];

        foreach ($query->get() as $index => $driver) {
            $certificate = $driver->has_certificate
                ? 'هەیەتی'.($driver->certificate_number ? ' ('.$driver->certificate_number.')' : '')
                : 'نییەتی';

            $rows[] = [
                $index + 1,
                $driver->name.($driver->blocked_at ? ' (شۆفێر بلۆککراوە)' : ''),
                $driver->phone,
                $driver->license_number,
                $certificate,
            ];
        }

        $path = XlsxWriter::create('شۆفێرەکان', $rows, [8, 30, 20, 24, 24]);

        return response()->download($path, 'drivers-'.now('Asia/Baghdad')->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
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

    public function block(Driver $driver)
    {
        $driver->update(['blocked_at' => now()]);

        return back()->with('success', 'شۆفێرەکە بلۆک کرا.');
    }

    public function unblock(Driver $driver)
    {
        $driver->update(['blocked_at' => null]);

        return back()->with('success', 'بلۆکی شۆفێرەکە لابرا.');
    }
}
