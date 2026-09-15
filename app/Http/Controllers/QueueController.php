<?php

namespace App\Http\Controllers;

use App\Models\GatekeeperSyncOperation;
use App\Models\Queue;
use App\Models\QueueArchive;
use App\Models\QueueArchiveItem;
use App\Models\Tanker;
use App\Support\XlsxWriter;
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

    public function filter(Request $request, $status)
    {
        $validStatuses = ['green', 'red', 'yellow', 'departed'];
        if (! in_array($status, $validStatuses)) {
            abort(404);
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $date = $validated['date'] ?? null;
        $snapshot = $this->snapshot();

        return view('gatekeeper.filter', compact('snapshot', 'status', 'date'));
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

    public function export()
    {
        $statusLabels = [
            'pending' => 'چاوەڕوان',
            'green' => 'هاتووە',
            'yellow' => 'دواخراو',
            'red' => 'نەهاتووە',
            'departed' => 'ڕۆیشتووە',
        ];
        $tankers = Tanker::query()
            ->with('latestQueue')
            ->orderByRaw('CAST(sequence_number AS UNSIGNED)')
            ->orderBy('sequence_number')
            ->get();

        $rows = [[
            'ڕیزبەندی',
            'ژمارەی تەنکەر',
            'خاوەنی خەت',
            'مۆبایل',
            'VIN',
            'جۆری بارھەڵگر',
            'مۆدێلی بارھەڵگر',
            'ڕەنگی بارھەڵگر',
            'دۆخ',
            'بەرواری دیاریکراو',
            'کاتی دیاریکراو',
            'تێبینی',
            'بلۆککراوە',
            'دوایین نوێکردنەوەی دۆخ',
        ]];

        foreach ($tankers as $tanker) {
            $queue = $tanker->latestQueue;
            $status = $queue?->status ?? 'pending';
            $rows[] = [
                $tanker->sequence_number,
                $tanker->plate_number,
                $tanker->sequence_owner,
                $tanker->sequence_owner_phone,
                $tanker->vin,
                $tanker->truck_type,
                $tanker->truck_model,
                $tanker->truck_color,
                $statusLabels[$status] ?? $status,
                $queue?->scheduled_date
                    ? Carbon::parse($queue->scheduled_date)->format('Y-m-d')
                    : null,
                $queue?->scheduled_time,
                $queue?->note,
                $tanker->blocked_at ? 'بەڵێ' : 'نەخێر',
                $queue?->updated_at?->timezone('Asia/Baghdad')->format('Y-m-d H:i:s'),
            ];
        }

        $path = XlsxWriter::create('کۆنترۆڵی دەروازە', $rows, [11, 18, 24, 17, 24, 18, 16, 16, 14, 18, 17, 30, 13, 23]);
        $fileName = 'gatekeeper_'.now('Asia/Baghdad')->format('Y_m_d_H_i_s').'.xlsx';

        return response()->download($path, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
        ])->deleteFileAfterSend(true);
    }

    public function history(Request $request)
    {
        $validated = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'in:pending,green,yellow,red,departed'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $today = now('Asia/Baghdad');
        $legacyMonth = isset($validated['month'])
            ? Carbon::createFromFormat('!Y-m', $validated['month'], 'Asia/Baghdad')
            : null;
        $fromDate = $validated['from_date']
            ?? $legacyMonth?->copy()->startOfMonth()->toDateString()
            ?? $today->copy()->startOfMonth()->toDateString();
        $toDate = $validated['to_date']
            ?? $legacyMonth?->copy()->endOfMonth()->toDateString()
            ?? $today->toDateString();
        if ($toDate < $fromDate) {
            throw ValidationException::withMessages([
                'to_date' => 'بەرواری کۆتایی نابێت پێش بەرواری دەستپێک بێت.',
            ]);
        }
        $fromUtc = Carbon::createFromFormat('!Y-m-d', $fromDate, 'Asia/Baghdad')->startOfDay()->utc();
        $toUtc = Carbon::createFromFormat('!Y-m-d', $toDate, 'Asia/Baghdad')->endOfDay()->utc();
        $showCurrent = $today->betweenIncluded(
            Carbon::createFromFormat('!Y-m-d', $fromDate, 'Asia/Baghdad')->startOfDay(),
            Carbon::createFromFormat('!Y-m-d', $toDate, 'Asia/Baghdad')->endOfDay(),
        );

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
                        ->orWhere('vin', 'like', "%{$search}%")
                        ->orWhere('truck_type', 'like', "%{$search}%")
                        ->orWhere('truck_model', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('CAST(sequence_number AS UNSIGNED)')
            ->orderBy('sequence_number');

        $currentTankers = $showCurrent
            ? $currentTankersQuery->get()
            : collect();

        $archiveIds = QueueArchive::query()
            ->whereBetween('reset_at', [$fromUtc, $toUtc])
            ->pluck('id');

        $items = QueueArchiveItem::query()
            ->with('archive.resetter')
            ->whereIn('queue_archive_id', $archiveIds)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('sequence_number', 'like', "%{$search}%")
                        ->orWhere('sequence_owner', 'like', "%{$search}%")
                        ->orWhere('sequence_owner_phone', 'like', "%{$search}%")
                        ->orWhere('plate_number', 'like', "%{$search}%")
                        ->orWhere('vin', 'like', "%{$search}%")
                        ->orWhere('truck_type', 'like', "%{$search}%")
                        ->orWhere('truck_model', 'like', "%{$search}%");
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

        return view('gatekeeper.history', compact('items', 'currentTankers', 'fromDate', 'toDate', 'showCurrent'));
    }

    public function updateStatus(Request $request, Tanker $tanker)
    {
        $validated = $request->validate([
            'status' => 'required|in:green,red,yellow,departed,pending',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $dataToUpdate = [
            'driver_id' => null,
            'status' => $validated['status'],
            'gatekeeper_id' => auth()->id(),
        ];

        if ($validated['status'] === 'departed') {
            $departedAt = now('Asia/Baghdad');
            $dataToUpdate['scheduled_date'] = $departedAt->toDateString();
            $dataToUpdate['scheduled_time'] = $departedAt->format('H:i');
        } elseif ($validated['status'] === 'red') {
            $dataToUpdate['scheduled_date'] = null;
            $dataToUpdate['scheduled_time'] = null;
        } else {
            if ($request->has('scheduled_date')) {
                $dataToUpdate['scheduled_date'] = $request->scheduled_date;
            }
            if ($request->has('scheduled_time')) {
                $dataToUpdate['scheduled_time'] = $request->scheduled_time;
            }
        }

        $queue = Queue::updateOrCreate(
            ['tanker_id' => $tanker->id],
            $dataToUpdate
        );

        return response()->json([
            'success' => true,
            'status' => $queue->status,
            'scheduled_date' => $queue->scheduled_date,
            'scheduled_time' => $queue->scheduled_time,
        ]);
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

    public function updatePhone(Request $request, Tanker $tanker)
    {
        $validated = $request->validate([
            'sequence_owner_phone' => ['nullable', 'string', 'max:255'],
        ]);

        $phone = filled($validated['sequence_owner_phone'] ?? null)
            ? trim($validated['sequence_owner_phone'])
            : null;
        $tanker->update(['sequence_owner_phone' => $phone]);

        return response()->json(['success' => true, 'sequence_owner_phone' => $phone]);
    }

    public function blockTanker(Tanker $tanker)
    {
        $tanker->update(['blocked_at' => now()]);

        return response()->json(['success' => true, 'blocked_at' => $tanker->blocked_at?->toIso8601String()]);
    }

    public function unblockTanker(Tanker $tanker)
    {
        $tanker->update(['blocked_at' => null]);

        return response()->json(['success' => true, 'blocked_at' => null]);
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
            'operations.*.payload.status' => 'nullable|in:green,red,yellow,departed,pending',
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
        $resetAt = now();
        $generatedAt = $resetAt->copy()->timezone('Asia/Baghdad');
        $fileName = 'tanker_queue_report_'.$generatedAt->format('Y_m_d_H_i_s').'.pdf';
        $filePath = 'reports/'.$fileName;
        $disk = Storage::disk('public');

        try {
            $pdfContents = DB::transaction(function () use ($disk, $filePath, $resetAt, $generatedAt) {
                // Lock the current queue snapshot so the PDF and reset describe
                // exactly the same records.
                Queue::query()->lockForUpdate()->get();

                $tankers = Tanker::with('latestQueue')
                    ->orderByRaw('CAST(sequence_number AS UNSIGNED)')
                    ->orderBy('sequence_number')
                    ->lockForUpdate()
                    ->get();

                $archive = QueueArchive::create([
                    'reset_by' => auth()->id(),
                    'reset_at' => $resetAt,
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
                        'truck_model' => $tanker->truck_model,
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
                    'title' => 'کەمپی حەسیرە - ڕاپۆرتی مانگانە',
                    'format' => 'A4-L',
                    'orientation' => 'L',
                    'default_font' => 'notosansarabic',
                    'default_font_size' => 10,
                    'margin_left' => 10,
                    'margin_right' => 10,
                    'margin_top' => 10,
                    'margin_bottom' => 13,
                    'custom_font_dir' => public_path('fonts'),
                    'custom_font_data' => [
                        'notosansarabic' => [
                            'R' => 'NotoSansArabic-Regular.ttf',
                            'B' => 'NotoSansArabic-Bold.ttf',
                            'useOTL' => 0xFF,
                            'useKashida' => 75,
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

        if ($payload['status'] === 'departed') {
            $departedAt = now('Asia/Baghdad');
            $data['scheduled_date'] = $departedAt->toDateString();
            $data['scheduled_time'] = $departedAt->format('H:i');
        } elseif ($payload['status'] === 'red') {
            $data['scheduled_date'] = null;
            $data['scheduled_time'] = null;
        } else {
            if (array_key_exists('scheduled_date', $payload)) {
                $data['scheduled_date'] = $payload['scheduled_date'];
            }

            if (array_key_exists('scheduled_time', $payload)) {
                $data['scheduled_time'] = $payload['scheduled_time'];
            }
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
            ->orderByRaw('CAST(sequence_number AS UNSIGNED)')
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
                'truck_model' => $tanker->truck_model,
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
