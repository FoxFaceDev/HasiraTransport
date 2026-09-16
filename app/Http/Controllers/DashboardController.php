<?php

namespace App\Http\Controllers;

use App\Models\Queue;
use App\Models\QueueArchiveItem;
use App\Models\Tanker;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        abort_unless(
            auth()->user()->can('view tankers') || auth()->user()->can('view gatekeeper'),
            403
        );

        $today = now('Asia/Baghdad')->startOfDay();
        $todayDate = $today->toDateString();
        $weekStart = $today->copy()->subDays(($today->dayOfWeek + 1) % 7);
        $weekEnd = $weekStart->copy()->addDays(6);

        $totalTankers = Tanker::query()->count();
        $blockedTankers = Tanker::query()->whereNotNull('blocked_at')->count();
        $queueCounts = Queue::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count);

        $statusCounts = [
            'pending' => max(0, $totalTankers - $queueCounts->sum()),
            'green' => $queueCounts->get('green', 0),
            'yellow' => $queueCounts->get('yellow', 0),
            'red' => $queueCounts->get('red', 0),
            'departed' => $queueCounts->get('departed', 0),
        ];

        $currentToday = Queue::query()
            ->with('tanker:id,sequence_number,plate_number,sequence_owner')
            ->whereDate('scheduled_date', $todayDate)
            ->whereIn('status', ['green', 'yellow', 'departed'])
            ->get();
        $archivedToday = QueueArchiveItem::query()
            ->whereDate('scheduled_date', $todayDate)
            ->whereIn('status', ['green', 'yellow', 'departed'])
            ->get();

        $todayRecords = $this->uniqueTruckRecords(
            $currentToday->map(fn (Queue $queue) => [
                'identity' => $queue->tanker_id ? 'tanker-'.$queue->tanker_id : 'plate-'.$queue->tanker?->plate_number,
                'tanker_id' => $queue->tanker_id,
                'sequence_number' => $queue->tanker?->sequence_number,
                'plate_number' => $queue->tanker?->plate_number,
                'sequence_owner' => $queue->tanker?->sequence_owner,
                'status' => $queue->status,
                'date' => $queue->scheduled_date,
                'time' => $queue->scheduled_time,
                'changed_at' => $queue->status_updated_at ?? $queue->updated_at,
            ])->concat($archivedToday->map(fn (QueueArchiveItem $item) => [
                'identity' => $item->tanker_id ? 'tanker-'.$item->tanker_id : 'plate-'.$item->plate_number,
                'tanker_id' => $item->tanker_id,
                'sequence_number' => $item->sequence_number,
                'plate_number' => $item->plate_number,
                'sequence_owner' => $item->sequence_owner,
                'status' => $item->status,
                'date' => $item->scheduled_date?->toDateString(),
                'time' => $item->scheduled_time,
                'changed_at' => $item->status_updated_at ?? $item->updated_at,
            ]))
        );

        $departedTodayRecords = $todayRecords
            ->where('status', 'departed')
            ->sortByDesc(fn (array $record) => $record['changed_at']?->getTimestamp() ?? 0)
            ->values();
        $scheduledToday = $todayRecords->count();
        $departedToday = $departedTodayRecords->count();
        $completionRate = $scheduledToday > 0
            ? (int) round(($departedToday / $scheduledToday) * 100)
            : 0;

        $timeSlots = [
            'early' => $todayRecords->filter(fn (array $record) => str_starts_with((string) $record['time'], '5:30'))->count(),
            'noon' => $todayRecords->filter(fn (array $record) => str_starts_with((string) $record['time'], '12:00'))->count(),
            'other' => $todayRecords->reject(fn (array $record) => str_starts_with((string) $record['time'], '5:30') || str_starts_with((string) $record['time'], '12:00'))->count(),
        ];

        $departureRecords = Queue::query()
            ->with('tanker:id,plate_number')
            ->where('status', 'departed')
            ->whereBetween('scheduled_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->map(fn (Queue $queue) => [
                'date' => $queue->scheduled_date,
                'identity' => $queue->tanker_id ? 'tanker-'.$queue->tanker_id : 'plate-'.$queue->tanker?->plate_number,
            ])
            ->concat(QueueArchiveItem::query()
                ->where('status', 'departed')
                ->whereBetween('scheduled_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->get()
                ->map(fn (QueueArchiveItem $item) => [
                    'date' => $item->scheduled_date?->toDateString(),
                    'identity' => $item->tanker_id ? 'tanker-'.$item->tanker_id : 'plate-'.$item->plate_number,
                ]));

        $departureTrend = collect(range(0, 6))->map(function (int $dayOffset) use ($weekStart, $departureRecords) {
            $date = $weekStart->copy()->addDays($dayOffset);
            $dateString = $date->toDateString();

            return [
                'date' => $dateString,
                'label' => $this->weekdayName($date),
                'count' => $departureRecords
                    ->where('date', $dateString)
                    ->unique('identity')
                    ->count(),
            ];
        });
        $maxTrend = max(1, (int) $departureTrend->max('count'));

        $upcomingSchedule = Queue::query()
            ->whereIn('status', ['green', 'yellow'])
            ->whereBetween('scheduled_date', [$todayDate, $today->copy()->addDays(6)->toDateString()])
            ->get()
            ->groupBy(fn (Queue $queue) => (string) $queue->scheduled_date)
            ->map(function (Collection $queues, string $date) {
                $carbonDate = Carbon::createFromFormat('!Y-m-d', $date, 'Asia/Baghdad');

                return [
                    'date' => $date,
                    'label' => $this->weekdayName($carbonDate),
                    'total' => $queues->count(),
                    'early' => $queues->filter(fn (Queue $queue) => str_starts_with((string) $queue->scheduled_time, '5:30'))->count(),
                    'noon' => $queues->filter(fn (Queue $queue) => str_starts_with((string) $queue->scheduled_time, '12:00'))->count(),
                ];
            })
            ->sortKeys()
            ->values();

        $recentChanges = Queue::query()
            ->with('tanker:id,sequence_number,plate_number,sequence_owner')
            ->whereNotNull('status_updated_at')
            ->orderByDesc('status_updated_at')
            ->limit(7)
            ->get();

        return view('dashboard', compact(
            'today',
            'totalTankers',
            'blockedTankers',
            'statusCounts',
            'scheduledToday',
            'departedToday',
            'departedTodayRecords',
            'completionRate',
            'timeSlots',
            'departureTrend',
            'maxTrend',
            'upcomingSchedule',
            'recentChanges',
        ));
    }

    private function uniqueTruckRecords(Collection $records): Collection
    {
        return $records
            ->sortByDesc(fn (array $record) => $record['changed_at']?->getTimestamp() ?? 0)
            ->unique('identity')
            ->values();
    }

    private function weekdayName(Carbon $date): string
    {
        return [
            'یەک شەممە',
            'دوو شەممە',
            'سێ شەممە',
            'چوار شەممە',
            'پێنج شەممە',
            'هەینی',
            'شەممە',
        ][$date->dayOfWeek];
    }
}
