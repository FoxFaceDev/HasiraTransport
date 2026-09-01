<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Tanker;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            $request->user()->can('view drivers') || $request->user()->can('view tankers') || $request->user()->can('view gatekeeper'),
            403
        );

        $drivers = $request->user()->can('view drivers') || $request->user()->can('view gatekeeper')
            ? Driver::query()->whereNotNull('blocked_at')->withCount('tankers')->orderByDesc('blocked_at')->get()
            : collect();

        $tankers = $request->user()->can('view tankers') || $request->user()->can('view gatekeeper')
            ? Tanker::query()->whereNotNull('blocked_at')->with('driver')->orderByDesc('blocked_at')->get()
            : collect();

        return view('blocks.index', compact('drivers', 'tankers'));
    }
}
