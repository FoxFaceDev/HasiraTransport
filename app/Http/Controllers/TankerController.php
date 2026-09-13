<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Tanker;
use App\Models\TankerTransfer;
use App\Support\TankerTransferDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

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
                    ->orWhere('truck_model', 'like', "%{$search}%")
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
            'truck_model' => 'nullable|string|max:255',
            'truck_color' => 'nullable|string|max:255',
        ]);

        Tanker::create([
            'sequence_number' => $request->sequence_number,
            'sequence_owner' => $request->sequence_owner,
            'sequence_owner_phone' => $request->sequence_owner_phone,
            'plate_number' => $request->plate_number,
            'vin' => $request->vin,
            'truck_type' => $request->truck_type,
            'truck_model' => $request->truck_model,
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
            'truck_model' => 'nullable|string|max:255',
            'truck_color' => 'nullable|string|max:255',
        ]);

        $newValues = [
            'sequence_number' => $request->sequence_number,
            'sequence_owner' => $request->sequence_owner,
            'sequence_owner_phone' => $request->sequence_owner_phone,
            'plate_number' => $request->plate_number,
            'vin' => $request->vin,
            'truck_type' => $request->truck_type,
            'truck_model' => $request->truck_model,
            'truck_color' => $request->truck_color,
        ];

        DB::transaction(function () use ($tanker, $newValues) {
            $lockedTanker = Tanker::query()->lockForUpdate()->findOrFail($tanker->id);
            $trackedFields = ['sequence_owner', 'sequence_owner_phone', 'plate_number', 'vin', 'truck_type', 'truck_model', 'truck_color'];

            if (collect($trackedFields)->contains(fn (string $field) => $lockedTanker->{$field} !== $newValues[$field])) {
                $this->recordTransfer($lockedTanker, $newValues, 'correction', [
                    'transferred_at' => now('Asia/Baghdad')->toDateString(),
                ]);
            }

            $lockedTanker->update($newValues);
        }, 3);

        return back()->with('success', 'زانیارییەکانی بارهەڵگر نوێکرایەوە.');
    }

    public function sell(Request $request, Tanker $tanker, TankerTransferDocument $document)
    {
        $validated = $request->validate([
            'operation_type' => ['required', Rule::in(['sale_with_truck', 'sale_line_only', 'truck_change'])],
            'document_number' => ['required', 'string', 'max:100'],
            'new_owner' => ['nullable', 'required_unless:operation_type,truck_change', 'string', 'max:255'],
            'new_owner_phone' => ['nullable', 'required_if:operation_type,sale_line_only', 'string', 'max:255'],
            'new_plate_number' => ['nullable', 'required_if:operation_type,sale_line_only,truck_change', 'string', 'max:255', Rule::unique('tankers', 'plate_number')->ignore($tanker->id)],
            'new_vin' => ['nullable', 'required_if:operation_type,sale_line_only,truck_change', 'string', 'max:255'],
            'new_truck_type' => ['nullable', 'required_if:operation_type,sale_line_only,truck_change', 'string', 'max:255'],
            'new_truck_model' => ['nullable', 'required_if:operation_type,sale_line_only,truck_change', 'string', 'max:255'],
            'new_truck_color' => ['nullable', 'required_if:operation_type,sale_line_only,truck_change', 'string', 'max:255'],
            'transferred_at' => ['required', 'date'],
            'seller_national_id' => ['nullable', 'string', 'max:255'],
            'seller_security_code' => ['nullable', 'string', 'max:255'],
            'seller_agent' => ['nullable', 'string', 'max:255'],
            'seller_agency_number' => ['nullable', 'string', 'max:255'],
            'seller_document_date' => ['nullable', 'date'],
            'buyer_national_id' => ['nullable', 'string', 'max:255'],
            'buyer_security_code' => ['nullable', 'string', 'max:255'],
            'buyer_agent' => ['nullable', 'string', 'max:255'],
            'buyer_agency_number' => ['nullable', 'string', 'max:255'],
            'buyer_document_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $documentPath = null;

        try {
            [$contents, $fileName] = DB::transaction(function () use ($tanker, $validated, $document, &$documentPath) {
                $lockedTanker = Tanker::query()->lockForUpdate()->findOrFail($tanker->id);
                $type = $validated['operation_type'];
                $changesOwner = $type !== 'truck_change';
                $changesTruck = $type !== 'sale_with_truck';
                $newValues = [
                    'sequence_owner' => $changesOwner ? $validated['new_owner'] : $lockedTanker->sequence_owner,
                    'sequence_owner_phone' => $changesOwner ? ($validated['new_owner_phone'] ?? null) : $lockedTanker->sequence_owner_phone,
                    'plate_number' => $changesTruck ? $validated['new_plate_number'] : $lockedTanker->plate_number,
                    'vin' => $changesTruck ? $validated['new_vin'] : $lockedTanker->vin,
                    'truck_type' => $changesTruck ? $validated['new_truck_type'] : $lockedTanker->truck_type,
                    'truck_model' => $changesTruck ? $validated['new_truck_model'] : $lockedTanker->truck_model,
                    'truck_color' => $changesTruck ? $validated['new_truck_color'] : $lockedTanker->truck_color,
                ];

                $transfer = $this->recordTransfer($lockedTanker, $newValues, $type, $validated);
                $lockedTanker->update($newValues);

                $contents = $document->render($transfer->fresh());
                $fileName = $type.'_'.$lockedTanker->sequence_number.'_'.$transfer->id.'.pdf';
                $documentPath = 'transfer-documents/'.$fileName;

                if (! Storage::disk('public')->put($documentPath, $contents)) {
                    throw new RuntimeException('The transfer document could not be archived.');
                }

                $transfer->update(['document_path' => $documentPath]);

                return [$contents, $fileName];
            }, 3);
        } catch (Throwable $exception) {
            if ($documentPath && Storage::disk('public')->exists($documentPath)) {
                Storage::disk('public')->delete($documentPath);
            }

            throw $exception;
        }

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function transferDocument(Tanker $tanker, TankerTransfer $transfer)
    {
        abort_unless($transfer->tanker_id === $tanker->id, 404);
        abort_unless($transfer->document_path && Storage::disk('public')->exists($transfer->document_path), 404);

        return response()->file(Storage::disk('public')->path($transfer->document_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($transfer->document_path).'"',
        ]);
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

    private function recordTransfer(Tanker $tanker, array $newValues, string $changeType, array $details): TankerTransfer
    {
        return $tanker->ownershipTransfers()->create([
            'recorded_by' => auth()->id(),
            'change_type' => $changeType,
            'document_number' => $details['document_number'] ?? null,
            'transferred_at' => $details['transferred_at'],
            'previous_owner' => $tanker->sequence_owner,
            'previous_owner_phone' => $tanker->sequence_owner_phone,
            'previous_plate_number' => $tanker->plate_number,
            'previous_vin' => $tanker->vin,
            'previous_truck_type' => $tanker->truck_type,
            'previous_truck_model' => $tanker->truck_model,
            'previous_truck_color' => $tanker->truck_color,
            'new_owner' => $newValues['sequence_owner'],
            'new_owner_phone' => $newValues['sequence_owner_phone'],
            'new_plate_number' => $newValues['plate_number'],
            'new_vin' => $newValues['vin'],
            'new_truck_type' => $newValues['truck_type'],
            'new_truck_model' => $newValues['truck_model'],
            'new_truck_color' => $newValues['truck_color'],
            'seller_national_id' => $details['seller_national_id'] ?? null,
            'seller_security_code' => $details['seller_security_code'] ?? null,
            'seller_agent' => $details['seller_agent'] ?? null,
            'seller_agency_number' => $details['seller_agency_number'] ?? null,
            'seller_document_date' => $details['seller_document_date'] ?? null,
            'buyer_national_id' => $details['buyer_national_id'] ?? null,
            'buyer_security_code' => $details['buyer_security_code'] ?? null,
            'buyer_agent' => $details['buyer_agent'] ?? null,
            'buyer_agency_number' => $details['buyer_agency_number'] ?? null,
            'buyer_document_date' => $details['buyer_document_date'] ?? null,
            'note' => $details['note'] ?? null,
        ]);
    }
}
