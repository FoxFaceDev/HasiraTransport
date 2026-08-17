<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        $files = \Illuminate\Support\Facades\Storage::disk('public')->files('reports');
        
        $reports = [];
        foreach ($files as $file) {
            $reports[] = [
                'name' => basename($file),
                'size' => round(\Illuminate\Support\Facades\Storage::disk('public')->size($file) / 1024, 2) . ' KB',
                'time' => \Carbon\Carbon::createFromTimestamp(\Illuminate\Support\Facades\Storage::disk('public')->lastModified($file))->format('Y-m-d H:i:s'),
                'path' => $file
            ];
        }

        // Sort descending by time
        usort($reports, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        return view('reports.index', compact('reports'));
    }

    public function download($fileName)
    {
        $filePath = 'reports/' . $fileName;
        
        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($filePath)) {
            abort(404);
        }

        $timestamp = \Illuminate\Support\Facades\Storage::disk('public')->lastModified($filePath);
        $displayName = 'ڕاپۆرتی_' . \Carbon\Carbon::createFromTimestamp($timestamp)->format('Y_m_d') . '.pdf';

        return response()->file(\Illuminate\Support\Facades\Storage::disk('public')->path($filePath), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . urlencode($displayName) . '"'
        ]);
    }
}
