<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Tanker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TankerController extends Controller
{
    public function index(Request $request)
    {
        $query = Tanker::query()->with('ownershipTransfers.recorder')->orderBy('id');

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

        $newValues = [
            'sequence_number' => $request->sequence_number,
            'sequence_owner' => $request->sequence_owner,
            'sequence_owner_phone' => $request->sequence_owner_phone,
            'plate_number' => $request->plate_number,
            'vin' => $request->vin,
            'truck_type' => $request->truck_type,
            'truck_color' => $request->truck_color,
        ];

        DB::transaction(function () use ($tanker, $newValues) {
            $lockedTanker = Tanker::query()->lockForUpdate()->findOrFail($tanker->id);
            $trackedFields = ['sequence_owner', 'sequence_owner_phone', 'plate_number', 'vin', 'truck_type', 'truck_color'];

            if (collect($trackedFields)->contains(fn (string $field) => $lockedTanker->{$field} !== $newValues[$field])) {
                $this->recordTransfer($lockedTanker, $newValues, 'correction', now()->toDateString(), null);
            }

            $lockedTanker->update($newValues);
        }, 3);

        return back()->with('success', 'زانیارییەکانی بارهەڵگر نوێکرایەوە.');
    }

    public function sell(Request $request, Tanker $tanker)
    {
        $validated = $request->validate([
            'new_owner' => ['required', 'string', 'max:255'],
            'new_owner_phone' => ['nullable', 'string', 'max:255'],
            'new_plate_number' => ['required', 'string', 'max:255', Rule::unique('tankers', 'plate_number')->ignore($tanker->id)],
            'new_vin' => ['nullable', 'string', 'max:255'],
            'new_truck_type' => ['required', 'string', 'max:255'],
            'new_truck_color' => ['nullable', 'string', 'max:255'],
            'transferred_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($tanker, $validated) {
            $lockedTanker = Tanker::query()->lockForUpdate()->findOrFail($tanker->id);
            $newValues = [
                'sequence_owner' => $validated['new_owner'],
                'sequence_owner_phone' => $validated['new_owner_phone'] ?? null,
                'plate_number' => $validated['new_plate_number'],
                'vin' => $validated['new_vin'] ?? null,
                'truck_type' => $validated['new_truck_type'],
                'truck_color' => $validated['new_truck_color'] ?? null,
            ];

            $this->recordTransfer($lockedTanker, $newValues, 'sale', $validated['transferred_at'], $validated['note'] ?? null);
            $lockedTanker->update($newValues);
        }, 3);

        return back()->with('success', 'فرۆشتنی خەتەکە تۆمار کرا و خاوەنەکەی گۆڕدرا.');
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

    private function recordTransfer(Tanker $tanker, array $newValues, string $changeType, string $transferredAt, ?string $note): void
    {
        $tanker->ownershipTransfers()->create([
            'recorded_by' => auth()->id(),
            'change_type' => $changeType,
            'transferred_at' => $transferredAt,
            'previous_owner' => $tanker->sequence_owner,
            'previous_owner_phone' => $tanker->sequence_owner_phone,
            'previous_plate_number' => $tanker->plate_number,
            'previous_vin' => $tanker->vin,
            'previous_truck_type' => $tanker->truck_type,
            'previous_truck_color' => $tanker->truck_color,
            'new_owner' => $newValues['sequence_owner'],
            'new_owner_phone' => $newValues['sequence_owner_phone'],
            'new_plate_number' => $newValues['plate_number'],
            'new_vin' => $newValues['vin'],
            'new_truck_type' => $newValues['truck_type'],
            'new_truck_color' => $newValues['truck_color'],
            'note' => $note,
        ]);
    }
}
