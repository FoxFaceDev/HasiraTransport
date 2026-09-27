<?php

namespace App\Http\Controllers;

use App\Models\Queue;
use App\Models\QueueArchiveItem;
use App\Models\QueueStatusEvent;
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
        $currentQueueCounts = Queue::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count);
        $assignedTankers = Queue::query()
            ->whereIn('status', ['green', 'yellow', 'red', 'departed'])
            ->distinct('tanker_id')
            ->count('tanker_id');

        $statusCounts = [
            'pending' => max(0, $totalTankers - $assignedTankers),
            'green' => $currentQueueCounts->get('green', 0),
            'yellow' => $currentQueueCounts->get('yellow', 0),
            'red' => $currentQueueCounts->get('red', 0),
            'departed' => $currentQueueCounts->get('departed', 0),
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

        $legacyTodayRecords = $this->uniqueTruckRecords(
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

        $todayEventRecords = QueueStatusEvent::query()
            ->with('tanker:id,sequence_number,plate_number,sequence_owner')
            ->whereNull('cancelled_at')
            ->whereDate('scheduled_date', $todayDate)
            ->whereIn('status', ['green', 'yellow', 'departed'])
            ->get()
            ->map(fn (QueueStatusEvent $event) => [
                'identity' => 'event-'.$event->id,
                'truck_identity' => 'tanker-'.$event->tanker_id,
                'tanker_id' => $event->tanker_id,
                'sequence_number' => $event->tanker?->sequence_number,
                'plate_number' => $event->tanker?->plate_number,
                'sequence_owner' => $event->tanker?->sequence_owner,
                'status' => $event->status,
                'date' => $event->scheduled_date?->toDateString(),
                'time' => $event->scheduled_time,
                'changed_at' => $event->occurred_at,
            ]);

        $eventTruckIdentities = $todayEventRecords->pluck('truck_identity')->unique();
        $todayRecords = $todayEventRecords
            ->concat($legacyTodayRecords->reject(
                fn (array $record) => $eventTruckIdentities->contains($record['identity'])
            ))
            ->sortByDesc(fn (array $record) => $record['changed_at']?->getTimestamp() ?? 0)
            ->values();

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

        $legacyDepartureRecords = Queue::query()
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

        $departureEventRecords = QueueStatusEvent::query()
            ->whereNull('cancelled_at')
            ->where('status', 'departed')
            ->whereBetween('scheduled_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->map(fn (QueueStatusEvent $event) => [
                'date' => $event->scheduled_date?->toDateString(),
                'identity' => 'event-'.$event->id,
                'truck_identity' => 'tanker-'.$event->tanker_id,
            ]);

        $eventDateTruckKeys = $departureEventRecords
            ->map(fn (array $record) => $record['date'].'|'.$record['truck_identity'])
            ->unique();
        $departureRecords = $departureEventRecords->concat(
            $legacyDepartureRecords
                ->unique(fn (array $record) => $record['date'].'|'.$record['identity'])
                ->reject(fn (array $record) => $eventDateTruckKeys->contains($record['date'].'|'.$record['identity']))
        );

        $departureTrend = collect(range(0, 6))->map(function (int $dayOffset) use ($weekStart, $departureRecords) {
            $date = $weekStart->copy()->addDays($dayOffset);
            $dateString = $date->toDateString();

            return [
                'date' => $dateString,
                'label' => $this->weekdayName($date),
                'count' => $departureRecords
                    ->where('date', $dateString)
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
