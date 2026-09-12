<?php

namespace App\Http\Controllers;

use App\Models\GatekeeperSyncOperation;
use App\Models\Queue;
use App\Models\QueueArchive;
use App\Models\QueueArchiveItem;
use App\Models\Tanker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use RuntimeException;
use Throwable;

class QueueController extends Controller
{
    public function index()
    {
        $snapshot = $this->snapshot();

        return view('gatekeeper.index', compact('snapshot'));
    }

    public function filter($status)
    {
        $validStatuses = ['green', 'red', 'yellow'];
        if (! in_array($status, $validStatuses)) {
            abort(404);
        }

        $snapshot = $this->snapshot();

        return view('gatekeeper.filter', compact('snapshot', 'status'));
    }

    public function schedule(Request $request)
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
        ]);
        $date = $validated['date'] ?? now('Asia/Baghdad')->toDateString();
        $snapshot = $this->snapshot();

        return view('gatekeeper.schedule', compact('snapshot', 'date'));
    }

    public function history(Request $request)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'in:pending,green,yellow,red'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $month = $validated['month'] ?? now('Asia/Baghdad')->format('Y-m');
        $selectedMonth = Carbon::createFromFormat('Y-m', $month, 'Asia/Baghdad')->startOfMonth();
        $fromUtc = $selectedMonth->copy()->startOfMonth()->utc();
        $toUtc = $selectedMonth->copy()->endOfMonth()->utc();

        $currentTankersQuery = Tanker::query()
            ->with('latestQueue')
            ->when($validated['status'] ?? null, function ($query, $status) {
                if ($status === 'pending') {
                    $query->where(function ($query) {
                        $query->whereDoesntHave('latestQueue')
                            ->orWhereHas('latestQueue', fn ($queue) => $queue->where('status', 'pending'));
                    });

                    return;
                }

                $query->whereHas('latestQueue', fn ($queue) => $queue->where('status', $status));
            })
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('sequence_number', 'like', "%{$search}%")
                        ->orWhere('sequence_owner', 'like', "%{$search}%")
                        ->orWhere('sequence_owner_phone', 'like', "%{$search}%")
                        ->orWhere('plate_number', 'like', "%{$search}%")
                        ->orWhere('vin', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('CAST(sequence_number AS UNSIGNED)')
            ->orderBy('sequence_number');

        $currentTankers = $month === now('Asia/Baghdad')->format('Y-m')
            ? $currentTankersQuery->get()
            : collect();

        $items = QueueArchiveItem::query()
            ->with('archive.resetter')
            ->whereHas('archive', fn ($query) => $query->whereBetween('reset_at', [
                $fromUtc,
                $toUtc,
            ]))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('sequence_number', 'like', "%{$search}%")
                        ->orWhere('sequence_owner', 'like', "%{$search}%")
                        ->orWhere('sequence_owner_phone', 'like', "%{$search}%")
                        ->orWhere('plate_number', 'like', "%{$search}%")
                        ->orWhere('vin', 'like', "%{$search}%");
                });
            })
            ->orderByDesc(
                QueueArchive::query()
                    ->select('reset_at')
                    ->whereColumn('queue_archives.id', 'queue_archive_items.queue_archive_id')
                    ->limit(1)
            )
            ->orderByRaw('CAST(sequence_number AS UNSIGNED)')
            ->orderBy('sequence_number')
            ->paginate(100)
            ->withQueryString();

        return view('gatekeeper.history', compact('items', 'currentTankers', 'month'));
    }

    public function updateStatus(Request $request, Tanker $tanker)
    {
        $request->validate([
            'status' => 'required|in:green,red,yellow,pending',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $dataToUpdate = [
            'driver_id' => null,
            'status' => $request->status,
            'gatekeeper_id' => auth()->id(),
        ];

        if ($request->has('scheduled_date')) {
            $dataToUpdate['scheduled_date'] = $request->scheduled_date;
        }
        if ($request->has('scheduled_time')) {
            $dataToUpdate['scheduled_time'] = $request->scheduled_time;
        }

        Queue::updateOrCreate(
            ['tanker_id' => $tanker->id],
            $dataToUpdate
        );

        return response()->json(['success' => true, 'status' => $request->status]);
    }

    public function updateNote(Request $request, Tanker $tanker)
    {
        $request->validate([
            'note' => 'nullable|string',
        ]);

        Queue::updateOrCreate(
            ['tanker_id' => $tanker->id],
            [
                'driver_id' => null,
                'gatekeeper_id' => auth()->id(),
                'note' => $request->note,
            ]
        );

        return response()->json(['success' => true]);
    }

    public function syncSnapshot()
    {
        return response()->json($this->snapshot());
    }

    public function syncPush(Request $request)
    {
        $validated = $request->validate([
            'operations' => 'present|array|max:250',
            'operations.*.operation_uuid' => 'required|uuid',
            'operations.*.type' => 'required|in:status,note',
            'operations.*.tanker_id' => 'required|integer|exists:tankers,id',
            'operations.*.payload' => 'required|array',
            'operations.*.payload.status' => 'nullable|in:green,red,yellow,pending',
            'operations.*.payload.scheduled_date' => 'nullable|date',
            'operations.*.payload.scheduled_time' => 'nullable|string|max:255',
            'operations.*.payload.note' => 'nullable|string',
            'operations.*.client_created_at' => 'nullable|date',
        ]);

        $accepted = DB::transaction(function () use ($validated, $request) {
            $accepted = [];

            foreach ($validated['operations'] as $operation) {
                if ($operation['type'] === 'status' && ! isset($operation['payload']['status'])) {
                    throw ValidationException::withMessages([
                        'operations' => 'A status operation must include a status value.',
                    ]);
                }

                $alreadyProcessed = GatekeeperSyncOperation::query()
                    ->where('operation_uuid', $operation['operation_uuid'])
                    ->exists();

                if ($alreadyProcessed) {
                    $accepted[] = $operation['operation_uuid'];

                    continue;
                }

                $tanker = Tanker::findOrFail($operation['tanker_id']);

                if ($operation['type'] === 'status') {
                    abort_unless($request->user()->can('update queue status'), 403);
                    $this->applyStatusOperation($tanker, $operation['payload'], $request->user()->id);
                } else {
                    abort_unless($request->user()->can('update queue notes'), 403);
                    $this->applyNoteOperation($tanker, $operation['payload'], $request->user()->id);
                }

                GatekeeperSyncOperation::create([
                    'operation_uuid' => $operation['operation_uuid'],
                    'user_id' => $request->user()->id,
                    'tanker_id' => $tanker->id,
                    'type' => $operation['type'],
                    'client_created_at' => $operation['client_created_at'] ?? null,
                ]);

                $accepted[] = $operation['operation_uuid'];
            }

            return $accepted;
        }, 3);

        return response()->json([
            'accepted' => $accepted,
            'snapshot' => $this->snapshot(),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    public function resetQueue()
    {
        $generatedAt = now();
        $fileName = 'tanker_queue_report_'.$generatedAt->format('Y_m_d_H_i_s').'.pdf';
        $filePath = 'reports/'.$fileName;
        $disk = Storage::disk('public');

        try {
            $pdfContents = DB::transaction(function () use ($disk, $filePath, $generatedAt) {
                // Lock the current queue snapshot so the PDF and reset describe
                // exactly the same records.
                Queue::query()->lockForUpdate()->get();

                $tankers = Tanker::with('latestQueue')
                    ->orderBy('sequence_number')
                    ->lockForUpdate()
                    ->get();

                $archive = QueueArchive::create([
                    'reset_by' => auth()->id(),
                    'reset_at' => $generatedAt,
                    'report_file' => $filePath,
                ]);

                $archive->items()->createMany($tankers->map(function (Tanker $tanker) {
                    $queue = $tanker->latestQueue;

                    return [
                        'tanker_id' => $tanker->id,
                        'sequence_number' => $tanker->sequence_number,
                        'sequence_owner' => $tanker->sequence_owner,
                        'sequence_owner_phone' => $tanker->sequence_owner_phone,
                        'plate_number' => $tanker->plate_number,
                        'vin' => $tanker->vin,
                        'truck_type' => $tanker->truck_type,
                        'truck_color' => $tanker->truck_color,
                        'status' => $queue?->status ?? 'pending',
                        'scheduled_date' => $queue?->scheduled_date,
                        'scheduled_time' => $queue?->scheduled_time,
                        'note' => $queue?->note,
                        'status_updated_at' => $queue?->updated_at,
                    ];
                })->all());

                $pdf = Pdf::loadView('pdf.queue_report', [
                    'tankers' => $tankers,
                    'generatedAt' => $generatedAt,
                    'generatedBy' => auth()->user(),
                ], [], [
                    'title' => 'ڕاپۆرتی تەنکەرەکان',
                    'format' => 'A4-L',
                    'orientation' => 'L',
                    'default_font' => 'notokufiarabic',
                    'default_font_size' => 10,
                    'margin_left' => 10,
                    'margin_right' => 10,
                    'margin_top' => 10,
                    'margin_bottom' => 13,
                    'custom_font_dir' => public_path('fonts'),
                    'custom_font_data' => [
                        'notokufiarabic' => [
                            'R' => 'NotoKufiArabic-Regular.ttf',
                            'B' => 'NotoKufiArabic-Bold.ttf',
                            // This variable font's GPOS table uses a lookup format
                            // unsupported by mPDF. Its Kurdish glyphs render correctly
                            // through mPDF's built-in RTL shaping without OTL parsing.
                            'useOTL' => 0,
                        ],
                    ],
                    'autoScriptToLang' => true,
                    'autoLangToFont' => false,
                    'useSubstitutions' => true,
                ]);

                $contents = $pdf->output();

                if (! $disk->put($filePath, $contents)) {
                    throw new RuntimeException('The queue report could not be archived.');
                }

                // DELETE is transactional, unlike TRUNCATE on MySQL.
                Queue::query()->delete();

                return $contents;
            }, 3);
        } catch (Throwable $exception) {
            if ($disk->exists($filePath)) {
                $disk->delete($filePath);
            }

            throw $exception;
        }

        return response($pdfContents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function applyStatusOperation(Tanker $tanker, array $payload, int $gatekeeperId): void
    {
        $data = [
            'driver_id' => null,
            'status' => $payload['status'],
            'gatekeeper_id' => $gatekeeperId,
        ];

        if (array_key_exists('scheduled_date', $payload)) {
            $data['scheduled_date'] = $payload['scheduled_date'];
        }

        if (array_key_exists('scheduled_time', $payload)) {
            $data['scheduled_time'] = $payload['scheduled_time'];
        }

        Queue::updateOrCreate(['tanker_id' => $tanker->id], $data);
    }

    private function applyNoteOperation(Tanker $tanker, array $payload, int $gatekeeperId): void
    {
        Queue::updateOrCreate(
            ['tanker_id' => $tanker->id],
            [
                'driver_id' => null,
                'gatekeeper_id' => $gatekeeperId,
                'note' => $payload['note'] ?? null,
            ]
        );
    }

    private function snapshot(): array
    {
        $tankers = Tanker::with('latestQueue')
            ->orderBy('sequence_number')
            ->get();

        return [
            'tankers' => $tankers->map(fn (Tanker $tanker) => [
                'id' => $tanker->id,
                'blocked_at' => $tanker->blocked_at?->toIso8601String(),
                'sequence_number' => $tanker->sequence_number,
                'sequence_owner' => $tanker->sequence_owner,
                'sequence_owner_phone' => $tanker->sequence_owner_phone,
                'plate_number' => $tanker->plate_number,
                'vin' => $tanker->vin,
                'truck_type' => $tanker->truck_type,
                'truck_color' => $tanker->truck_color,
                'queue' => $tanker->latestQueue ? [
                    'status' => $tanker->latestQueue->status,
                    'scheduled_date' => $tanker->latestQueue->scheduled_date,
                    'scheduled_time' => $tanker->latestQueue->scheduled_time,
                    'note' => $tanker->latestQueue->note,
                    'updated_at' => $tanker->latestQueue->updated_at?->toIso8601String(),
                ] : [
                    'status' => 'pending',
                    'scheduled_date' => null,
                    'scheduled_time' => null,
                    'note' => null,
                    'updated_at' => null,
                ],
            ])->values(),
            'synced_at' => now()->toIso8601String(),
        ];
    }
}
