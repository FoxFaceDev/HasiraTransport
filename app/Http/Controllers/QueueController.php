<?php

namespace App\Http\Controllers;

use App\Models\Queue;
use App\Models\Tanker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use RuntimeException;
use Throwable;

class QueueController extends Controller
{
    public function index()
    {
        $tankers = Tanker::with(['driver', 'latestQueue'])
            ->orderBy('sequence_number')
            ->get();

        return view('gatekeeper.index', compact('tankers'));
    }

    public function filter($status)
    {
        $validStatuses = ['green', 'red', 'yellow'];
        if (! in_array($status, $validStatuses)) {
            abort(404);
        }

        $queues = Queue::with(['tanker.driver'])
            ->where('status', $status)
            ->latest()
            ->get();

        return view('gatekeeper.filter', compact('queues', 'status'));
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
            'driver_id' => $tanker->driver ? $tanker->driver->id : null,
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
                'driver_id' => $tanker->driver ? $tanker->driver->id : null,
                'gatekeeper_id' => auth()->id(),
                'note' => $request->note,
            ]
        );

        return response()->json(['success' => true]);
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

                $tankers = Tanker::with(['driver', 'latestQueue'])
                    ->orderBy('sequence_number')
                    ->lockForUpdate()
                    ->get();

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
}
