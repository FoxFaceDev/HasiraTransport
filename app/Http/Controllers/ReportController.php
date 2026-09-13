<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function index()
    {
        $disk = Storage::disk('public');
        $files = $disk->files('reports');

        $reports = [];
        foreach ($files as $file) {
            $reports[] = [
                'name' => basename($file),
                'size' => round($disk->size($file) / 1024, 2).' KB',
                'time' => Carbon::createFromTimestamp($disk->lastModified($file), 'Asia/Baghdad')->format('Y-m-d H:i:s'),
                'path' => $file,
            ];
        }

        // Sort descending by time
        usort($reports, function ($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        return view('reports.index', compact('reports'));
    }

    public function download($fileName)
    {
        $filePath = 'reports/'.$fileName;
        $disk = Storage::disk('public');

        if (! $disk->exists($filePath)) {
            abort(404);
        }

        $timestamp = $disk->lastModified($filePath);
        $displayName = 'ڕاپۆرتی_'.Carbon::createFromTimestamp($timestamp, 'Asia/Baghdad')->format('Y_m_d').'.pdf';

        return response()->file($disk->path($filePath), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.urlencode($displayName).'"',
        ]);
    }
}
