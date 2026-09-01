<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'method' => ['nullable', 'in:POST,PUT,PATCH,DELETE'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = AuditLog::query()->latest('id');

        $query->when($validated['search'] ?? null, function ($query, string $search) {
            $query->where(function ($query) use ($search) {
                $query->where('user_name', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%")
                    ->orWhere('route_name', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('subject_label', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        });

        $query->when($validated['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId));
        $query->when($validated['method'] ?? null, fn ($query, $method) => $query->where('method', $method));
        $timezone = config('audit.timezone');
        $query->when($validated['from'] ?? null, fn ($query, $from) => $query->where(
            'created_at',
            '>=',
            CarbonImmutable::parse($from, $timezone)->startOfDay()->utc()
        ));
        $query->when($validated['to'] ?? null, fn ($query, $to) => $query->where(
            'created_at',
            '<=',
            CarbonImmutable::parse($to, $timezone)->endOfDay()->utc()
        ));

        $todayStart = CarbonImmutable::now($timezone)->startOfDay()->utc();
        $todayEnd = CarbonImmutable::now($timezone)->endOfDay()->utc();

        return view('audit-logs.index', [
            'logs' => $query->paginate(50)->withQueryString(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'totalLogs' => AuditLog::query()->count(),
            'todayLogs' => AuditLog::query()->whereBetween('created_at', [$todayStart, $todayEnd])->count(),
            'todayChanges' => AuditLog::query()
                ->whereBetween('created_at', [$todayStart, $todayEnd])
                ->whereIn('method', ['POST', 'PUT', 'PATCH', 'DELETE'])
                ->count(),
        ]);
    }
}
