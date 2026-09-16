<?php

use App\Models\GatekeeperSyncOperation;
use App\Models\Queue;
use App\Models\QueueArchive;
use App\Models\QueueArchiveItem;
use App\Models\Tanker;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->gatekeeper = User::factory()->create();
    $this->gatekeeper->assignRole('gatekeeper');

    $this->tanker = Tanker::create([
        'sequence_number' => '12',
        'sequence_owner' => 'Owner',
        'sequence_owner_phone' => '07501112233',
        'plate_number' => 'TEST-100',
        'vin' => 'VIN-OFFLINE-1',
        'truck_type' => 'Tanker',
        'truck_model' => '2020',
        'truck_color' => 'White',
    ]);
});

it('returns the complete gatekeeper snapshot', function () {
    $this->actingAs($this->gatekeeper)
        ->getJson(route('gatekeeper.sync.snapshot'))
        ->assertOk()
        ->assertJsonPath('tankers.0.id', $this->tanker->id)
        ->assertJsonPath('tankers.0.blocked_at', null)
        ->assertJsonPath('tankers.0.sequence_owner', 'Owner')
        ->assertJsonPath('tankers.0.truck_type', 'Tanker')
        ->assertJsonPath('tankers.0.truck_model', '2020')
        ->assertJsonPath('tankers.0.sequence_owner_phone', '07501112233')
        ->assertJsonMissingPath('tankers.0.driver')
        ->assertJsonPath('tankers.0.queue.status', 'pending');
});

it('exports the complete gatekeeper table as a right-to-left Excel workbook', function () {
    Queue::create([
        'tanker_id' => $this->tanker->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'yellow',
        'scheduled_date' => '2026-09-15',
        'scheduled_time' => '10:30',
        'note' => 'تێبینی تاقیکردنەوە',
    ]);

    $response = $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.export'))
        ->assertOk()
        ->assertDownload();

    $path = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;

    expect($zip->open($path))->toBeTrue();

    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $workbook = $zip->getFromName('xl/workbook.xml');
    $zip->close();

    expect($sheet)
        ->toContain('rightToLeft="1"')
        ->toContain('TEST-100')
        ->toContain('VIN-OFFLINE-1')
        ->toContain('دواخراو')
        ->toContain('تێبینی تاقیکردنەوە')
        ->and($workbook)->toContain('کۆنترۆڵی دەروازە');
});

it('embeds the initial truck snapshot in the gatekeeper page', function () {
    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.index'))
        ->assertOk()
        ->assertSee('gatekeeperQueueManager', false)
        ->assertSee('TEST-100', false)
        ->assertSee('Owner', false)
        ->assertSee('07501112233', false)
        ->assertSee('خاوەنی خەت')
        ->assertSee('مۆبایل')
        ->assertDontSee('ناوی شۆفێر')
        ->assertDontSee('شەهادە')
        ->assertSee('isBlocked(actionsTanker)', false)
        ->assertSee('blocked-badge', false)
        ->assertDontSee('<th class="py-3 px-4 font-normal">#</th>', false)
        ->assertDontSee('x-text="index + 1"', false);
});

it('does not advertise or render offline queue controls', function () {
    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.index'))
        ->assertOk()
        ->assertSee('گۆڕانکارییەکان ڕاستەوخۆ لە سێرڤەر هەڵدەگیرێن.')
        ->assertDontSee('گۆڕانکاری چاوەڕێی هاوکاتکردنەوەیە')
        ->assertDontSee('@click="syncNow()"', false)
        ->assertDontSee('داتای ئۆفلاین ئامادە دەکرێت...');
});

it('uses the ranking label throughout the gatekeeper tables and monthly PDF', function () {
    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.index'))
        ->assertOk()
        ->assertSee('ڕیزبەندی')
        ->assertDontSee('زنجیرە');

    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.schedule', ['date' => '2026-09-02']))
        ->assertOk()
        ->assertSee('ڕیزبەندی')
        ->assertDontSee('زنجیرە');

    $html = view('pdf.queue_report', [
        'tankers' => collect([$this->tanker->load('latestQueue')]),
        'generatedAt' => now(),
        'generatedBy' => $this->gatekeeper,
    ])->render();

    expect($html)
        ->toContain('کەمپی حەسیرە')
        ->toContain('ڕاپۆرتی مانگانە')
        ->toContain('ڕیزبەندی')
        ->toContain('data:image/svg+xml;base64,')
        ->not->toContain('زنجیرە');
});

it('opens gatekeeper actions in one modal and reverts a selected status from it', function () {
    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.index'))
        ->assertOk()
        ->assertSee('@click="openActionsModal(tanker)"', false)
        ->assertSee('x-show="showActionsModal"', false)
        ->assertSee("getStatus(actionsTanker) === 'green' ? revertStatus(actionsTanker.id)", false)
        ->assertSee("getStatus(actionsTanker) === 'yellow' ? revertStatus(actionsTanker.id)", false)
        ->assertSee("getStatus(actionsTanker) === 'red' ? revertStatus(actionsTanker.id)", false)
        ->assertSee("getStatus(actionsTanker) === 'departed' ? revertStatus(actionsTanker.id) : updateStatus(actionsTanker.id, 'departed')", false)
        ->assertDontSee("openScheduleModal(actionsTanker.id, 'departed')", false)
        ->assertDontSee('>گەڕاندنەوە</button>', false);
});

it('renders the dated schedule page and links it from the sidebar', function () {
    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.schedule', ['date' => '2026-09-02']))
        ->assertOk()
        ->assertSee('لیستی ئەمڕۆ')
        ->assertSee('type="date"', false)
        ->assertSee('scheduleOnly: true', false)
        ->assertSee("dateFilter: '2026-09-02'", false)
        ->assertSee('getStatusLabel(tanker)', false)
        ->assertSee('getScheduleRowClass(tanker)', false)
        ->assertSee(route('gatekeeper.schedule'), false);
});

it('includes trucks departed on the selected day in todays list and offers both sorts', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-09-16 14:30:00', 'Asia/Baghdad'));

    $this->actingAs($this->gatekeeper)
        ->postJson(route('gatekeeper.update-status', $this->tanker), ['status' => 'departed'])
        ->assertOk();

    $this->get(route('gatekeeper.schedule', ['date' => '2026-09-16']))
        ->assertOk()
        ->assertSee('TEST-100', false)
        ->assertSee('\u0022status\u0022:\u0022departed\u0022', false)
        ->assertSee('بەپێی ژمارەی ڕیزبەندی')
        ->assertSee('لە نوێوە بۆ کۆن')
        ->assertSee('لە کۆنەوە بۆ نوێ')
        ->assertSee(':href="exportUrl"', false);

    expect(file_get_contents(resource_path('js/gatekeeper.js')))
        ->toContain("['green', 'yellow', 'departed']")
        ->toContain("['newest', 'oldest'].includes(this.sortMode)");
});

it('shows the sorting choices on the main and every status list', function (string $route) {
    $this->actingAs($this->gatekeeper)
        ->get($route)
        ->assertOk()
        ->assertSee('x-model="sortMode"', false)
        ->assertSee('بەپێی ژمارەی ڕیزبەندی')
        ->assertSee('لە نوێوە بۆ کۆن')
        ->assertSee('لە کۆنەوە بۆ نوێ')
        ->assertSee(':href="exportUrl"', false);
})->with([
    'main list' => fn () => route('gatekeeper.index'),
    'arrived list' => fn () => route('gatekeeper.filter', 'green'),
    'delayed list' => fn () => route('gatekeeper.filter', 'yellow'),
    'not-arrived list' => fn () => route('gatekeeper.filter', 'red'),
    'departed list' => fn () => route('gatekeeper.filter', 'departed'),
]);

it('defaults the delayed list to date and time groups with day dividers', function () {
    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.filter', 'yellow'))
        ->assertOk()
        ->assertSee("sortMode: 'scheduled'", false)
        ->assertSee('<option value="scheduled">بەپێی ڕۆژ و کات</option>', false)
        ->assertSee('getScheduleDayDividerClass(index, tanker)', false)
        ->assertSee('getScheduledDateLabel(tanker)', false);

    $script = file_get_contents(resource_path('js/gatekeeper.js'));

    expect($script)
        ->toContain("time.startsWith('5:30')")
        ->toContain("time.startsWith('12:00')")
        ->toContain('schedule-day-divider')
        ->toContain('queue-row-yellow-early')
        ->toContain('queue-row-yellow-late')
        ->and($script)->toContain('شەممە')
        ->toContain('یەک شەممە')
        ->toContain('دوو شەممە')
        ->toContain('سێ شەممە')
        ->toContain('چوار شەممە')
        ->toContain('پێنج شەممە')
        ->toContain('هەینی');

    expect(file_get_contents(resource_path('css/app.css')))
        ->toContain('tr.queue-row-yellow-early > td')
        ->toContain('tr.queue-row-yellow-late > td')
        ->toContain('tr.schedule-day-divider > td');
});

it('exports delayed trucks by date with 5:30 before 12:00', function () {
    Queue::create([
        'tanker_id' => $this->tanker->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'yellow',
        'scheduled_date' => '2026-09-20',
        'scheduled_time' => '12:00 نیوەڕۆ',
    ]);

    $earlyTanker = Tanker::create([
        'sequence_number' => '2',
        'sequence_owner' => 'Early owner',
        'plate_number' => 'SUNDAY-0530',
        'truck_type' => 'Tanker',
    ]);
    Queue::create([
        'tanker_id' => $earlyTanker->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'yellow',
        'scheduled_date' => '2026-09-20',
        'scheduled_time' => '5:30 بەیانی',
    ]);

    $nextDayTanker = Tanker::create([
        'sequence_number' => '1',
        'sequence_owner' => 'Next day owner',
        'plate_number' => 'MONDAY-0530',
        'truck_type' => 'Tanker',
    ]);
    Queue::create([
        'tanker_id' => $nextDayTanker->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'yellow',
        'scheduled_date' => '2026-09-21',
        'scheduled_time' => '5:30 بەیانی',
    ]);

    $response = $this->actingAs($this->gatekeeper)->get(route('gatekeeper.export', [
        'status' => 'yellow',
        'sort' => 'scheduled',
    ]))->assertOk();

    $zip = new ZipArchive;
    expect($zip->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    expect(strpos($sheet, 'SUNDAY-0530'))
        ->toBeLessThan(strpos($sheet, 'TEST-100'))
        ->and(strpos($sheet, 'TEST-100'))
        ->toBeLessThan(strpos($sheet, 'MONDAY-0530'));
});

it('exports the selected list with its date filter and oldest-to-newest sorting', function () {
    Queue::create([
        'tanker_id' => $this->tanker->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'green',
        'scheduled_date' => '2026-09-16',
        'scheduled_time' => '09:00',
        'status_updated_at' => '2026-09-16 09:00:00',
    ]);

    $newerTanker = Tanker::create([
        'sequence_number' => '1',
        'sequence_owner' => 'Newer owner',
        'plate_number' => 'NEWER-200',
        'truck_type' => 'Tanker',
    ]);
    Queue::create([
        'tanker_id' => $newerTanker->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'departed',
        'scheduled_date' => '2026-09-16',
        'scheduled_time' => '10:00',
        'status_updated_at' => '2026-09-16 10:00:00',
    ]);

    $excludedTanker = Tanker::create([
        'sequence_number' => '2',
        'sequence_owner' => 'Excluded owner',
        'plate_number' => 'EXCLUDED-300',
        'truck_type' => 'Tanker',
    ]);
    Queue::create([
        'tanker_id' => $excludedTanker->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'red',
        'status_updated_at' => '2026-09-16 11:00:00',
    ]);

    $response = $this->actingAs($this->gatekeeper)->get(route('gatekeeper.export', [
        'schedule' => 1,
        'date' => '2026-09-16',
        'sort' => 'oldest',
    ]))->assertOk();

    $zip = new ZipArchive;
    expect($zip->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    expect($sheet)
        ->toContain('TEST-100')
        ->toContain('NEWER-200')
        ->not->toContain('EXCLUDED-300')
        ->and(strpos($sheet, 'TEST-100'))->toBeLessThan(strpos($sheet, 'NEWER-200'));
});

it('rejects an invalid schedule date', function () {
    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.schedule', ['date' => 'not-a-date']))
        ->assertSessionHasErrors('date');
});

it('renders realtime search on every status page', function (string $status) {
    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.filter', $status))
        ->assertOk()
        ->assertSee('type="search"', false)
        ->assertSee('@input.debounce.100ms="search = $event.target.value"', false)
        ->assertSee('autocomplete="off"', false);
})->with(['green', 'yellow', 'red', 'departed']);

it('orders delayed trucks from the first status change to the last', function () {
    $laterTanker = Tanker::create([
        'sequence_number' => '1',
        'sequence_owner' => 'Later owner',
        'plate_number' => 'DELAYED-LATER',
        'truck_type' => 'Tanker',
    ]);

    $this->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00', 'Asia/Baghdad'));
    $this->actingAs($this->gatekeeper)
        ->postJson(route('gatekeeper.update-status', $this->tanker), ['status' => 'yellow'])
        ->assertOk()
        ->assertJsonPath('status_updated_at', fn ($value) => filled($value));

    $firstChangedAt = $this->tanker->fresh()->latestQueue->status_updated_at;

    $this->travelTo(\Carbon\Carbon::parse('2026-09-15 10:00:00', 'Asia/Baghdad'));
    $this->actingAs($this->gatekeeper)
        ->postJson(route('gatekeeper.update-status', $laterTanker), ['status' => 'yellow'])
        ->assertOk();

    $this->postJson(route('gatekeeper.update-note', $this->tanker), ['note' => 'Changed later'])
        ->assertOk();

    expect($this->tanker->fresh()->latestQueue->status_updated_at->equalTo($firstChangedAt))->toBeTrue();

    $this->get(route('gatekeeper.filter', 'yellow'))
        ->assertOk()
        ->assertSeeInOrder(['TEST-100', 'DELAYED-LATER']);
});

it('filters dated statuses and renders the departed page', function () {
    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.filter', ['status' => 'green', 'date' => '2026-09-15']))
        ->assertOk()
        ->assertSee("filterDate: '2026-09-15'", false)
        ->assertSee('فلتەر بە پێی بەروار');

    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.filter', ['status' => 'departed', 'date' => '2026-09-16']))
        ->assertOk()
        ->assertSee('لیستی ڕۆیشتووەکان')
        ->assertSee("statusFilter: 'departed'", false);
});

it('saves the departed status with its date', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-09-15 16:42:00', 'Asia/Baghdad'));

    $this->actingAs($this->gatekeeper)
        ->postJson(route('gatekeeper.update-status', $this->tanker), [
            'status' => 'departed',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'departed')
        ->assertJsonPath('scheduled_date', '2026-09-15')
        ->assertJsonPath('scheduled_time', '16:42');

    expect($this->tanker->fresh()->latestQueue->status)->toBe('departed')
        ->and($this->tanker->fresh()->latestQueue->scheduled_date)->toBe('2026-09-15')
        ->and($this->tanker->fresh()->latestQueue->scheduled_time)->toBe('16:42');
});

it('clears an existing date and time when marking a tanker as not arrived', function () {
    Queue::create([
        'tanker_id' => $this->tanker->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'yellow',
        'scheduled_date' => '2026-09-18',
        'scheduled_time' => '5:30 بەیانی',
    ]);

    $this->actingAs($this->gatekeeper)
        ->postJson(route('gatekeeper.update-status', $this->tanker), ['status' => 'red'])
        ->assertOk()
        ->assertJsonPath('status', 'red')
        ->assertJsonPath('scheduled_date', null)
        ->assertJsonPath('scheduled_time', null);

    $queue = $this->tanker->fresh()->latestQueue;

    expect($queue->status)->toBe('red')
        ->and($queue->scheduled_date)->toBeNull()
        ->and($queue->scheduled_time)->toBeNull();
});

it('lets only permitted users update a phone from the gatekeeper page', function () {
    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.index'))
        ->assertOk()
        ->assertSee('@contextmenu.prevent="editPhone(tanker)"', false);

    $this->patchJson(route('gatekeeper.update-phone', $this->tanker), [
        'sequence_owner_phone' => '07509998877',
    ])->assertOk()->assertJsonPath('sequence_owner_phone', '07509998877');

    expect($this->tanker->fresh()->sequence_owner_phone)->toBe('07509998877');

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('view gatekeeper');

    $this->actingAs($viewer)
        ->get(route('gatekeeper.index'))
        ->assertOk()
        ->assertDontSee('@contextmenu.prevent="editPhone(tanker)"', false);

    $this->actingAs($viewer)
        ->patchJson(route('gatekeeper.update-phone', $this->tanker), ['sequence_owner_phone' => '000'])
        ->assertForbidden();
});

it('rejects a reversed history date range', function () {
    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.history', [
            'from_date' => '2026-09-20',
            'to_date' => '2026-09-10',
        ]))
        ->assertSessionHasErrors('to_date');
});

it('lets a permitted gatekeeper block and unblock a tanker', function () {
    $this->actingAs($this->gatekeeper)
        ->patchJson(route('gatekeeper.block-tanker', $this->tanker))
        ->assertOk()
        ->assertJsonPath('blocked_at', fn ($value) => filled($value));

    expect($this->tanker->fresh()->blocked_at)->not->toBeNull();

    $this->deleteJson(route('gatekeeper.unblock-tanker', $this->tanker))
        ->assertOk()
        ->assertJsonPath('blocked_at', null);

    expect($this->tanker->fresh()->blocked_at)->toBeNull();
});

it('applies queued operations exactly once', function () {
    $statusUuid = (string) Str::uuid();
    $noteUuid = (string) Str::uuid();
    $payload = [
        'operations' => [
            [
                'operation_uuid' => $statusUuid,
                'type' => 'status',
                'tanker_id' => $this->tanker->id,
                'payload' => [
                    'status' => 'yellow',
                    'scheduled_date' => '2026-08-18',
                    'scheduled_time' => '5:30 بەیانی',
                ],
                'client_created_at' => now()->toIso8601String(),
            ],
            [
                'operation_uuid' => $noteUuid,
                'type' => 'note',
                'tanker_id' => $this->tanker->id,
                'payload' => ['note' => 'Offline note'],
                'client_created_at' => now()->addSecond()->toIso8601String(),
            ],
        ],
    ];

    $this->actingAs($this->gatekeeper)
        ->postJson(route('gatekeeper.sync.push'), $payload)
        ->assertOk()
        ->assertJsonPath('accepted.0', $statusUuid)
        ->assertJsonPath('accepted.1', $noteUuid);

    $this->actingAs($this->gatekeeper)
        ->postJson(route('gatekeeper.sync.push'), $payload)
        ->assertOk();

    $queue = Queue::where('tanker_id', $this->tanker->id)->firstOrFail();

    expect($queue->status)->toBe('yellow')
        ->and($queue->note)->toBe('Offline note')
        ->and(GatekeeperSyncOperation::count())->toBe(2);
});

it('reverts a gatekeeper status to pending and clears its schedule', function () {
    Queue::create([
        'tanker_id' => $this->tanker->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'green',
        'scheduled_date' => '2026-08-18',
        'scheduled_time' => '5:30 بەیانی',
    ]);

    $this->actingAs($this->gatekeeper)
        ->postJson(route('gatekeeper.sync.push'), [
            'operations' => [[
                'operation_uuid' => (string) Str::uuid(),
                'type' => 'status',
                'tanker_id' => $this->tanker->id,
                'payload' => [
                    'status' => 'pending',
                    'scheduled_date' => null,
                    'scheduled_time' => null,
                ],
                'client_created_at' => now()->toIso8601String(),
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('snapshot.tankers.0.queue.status', 'pending')
        ->assertJsonPath('snapshot.tankers.0.queue.scheduled_date', null)
        ->assertJsonPath('snapshot.tankers.0.queue.scheduled_time', null);

    $queue = Queue::where('tanker_id', $this->tanker->id)->firstOrFail();

    expect($queue->status)->toBe('pending')
        ->and($queue->scheduled_date)->toBeNull()
        ->and($queue->scheduled_time)->toBeNull();
});

it('rejects status synchronization without status permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('view gatekeeper');

    $this->actingAs($viewer)
        ->postJson(route('gatekeeper.sync.push'), [
            'operations' => [[
                'operation_uuid' => (string) Str::uuid(),
                'type' => 'status',
                'tanker_id' => $this->tanker->id,
                'payload' => ['status' => 'green'],
                'client_created_at' => now()->toIso8601String(),
            ]],
        ])
        ->assertForbidden();

    expect(Queue::count())->toBe(0)
        ->and(GatekeeperSyncOperation::count())->toBe(0);
});

it('archives every truck status during reset and filters the history by month', function () {
    Queue::create([
        'tanker_id' => $this->tanker->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'yellow',
        'scheduled_date' => '2026-09-15',
        'scheduled_time' => '10:30',
        'note' => 'Delayed',
    ]);
    $pendingTanker = Tanker::create([
        'sequence_number' => '13',
        'sequence_owner' => 'Pending Owner',
        'plate_number' => 'TEST-101',
        'truck_type' => 'Tanker',
    ]);
    Storage::fake('public');
    $this->travelTo(now()->setDate(2026, 9, 12)->setTime(9, 0));

    $this->actingAs($this->gatekeeper)
        ->post(route('gatekeeper.reset'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(Queue::query()->count())->toBe(0)
        ->and(QueueArchive::query()->count())->toBe(1)
        ->and(QueueArchiveItem::query()->count())->toBe(2)
        ->and(QueueArchive::query()->value('report_file'))->toContain('2026_09_12_12_00_00')
        ->and(QueueArchiveItem::query()->where('tanker_id', $this->tanker->id)->value('status'))->toBe('yellow')
        ->and(QueueArchiveItem::query()->where('tanker_id', $this->tanker->id)->value('truck_model'))->toBe('2020')
        ->and(QueueArchiveItem::query()->where('tanker_id', $pendingTanker->id)->value('status'))->toBe('pending');

    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.history', ['month' => '2026-09', 'status' => 'yellow']))
        ->assertOk()
        ->assertSee('TEST-100')
        ->assertSee('Delayed')
        ->assertDontSee('TEST-101');
});

it('orders the reset archive and PDF data numerically by ranking', function () {
    foreach ([10, 2] as $ranking) {
        Tanker::create([
            'sequence_number' => (string) $ranking,
            'sequence_owner' => 'Owner '.$ranking,
            'plate_number' => 'RESET-RANK-'.$ranking,
            'truck_type' => 'Tanker',
        ]);
    }

    Storage::fake('public');

    $this->actingAs($this->gatekeeper)
        ->post(route('gatekeeper.reset'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(QueueArchiveItem::query()->orderBy('id')->pluck('sequence_number')->all())
        ->toBe(['2', '10', '12']);
});

it('includes the current not-yet-reset trucks in status history', function () {
    Queue::create([
        'tanker_id' => $this->tanker->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'green',
        'note' => 'Current live status',
    ]);

    expect(QueueArchive::query()->count())->toBe(0);

    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.history', ['from_date' => now('Asia/Baghdad')->startOfMonth()->toDateString(), 'to_date' => now('Asia/Baghdad')->toDateString(), 'status' => 'green']))
        ->assertOk()
        ->assertSee('لیستی ئێستا و مێژووی ماوە')
        ->assertSee('TEST-100')
        ->assertSee('2020')
        ->assertSee('Current live status');

    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.history', ['month' => '2025-01', 'status' => 'green']))
        ->assertOk()
        ->assertDontSee('TEST-100')
        ->assertDontSee('لیستی ئێستا و مێژووی مانگ');
});

it('shows every reset archive inside a date range', function () {
    Storage::fake('public');

    foreach ([
        ['2026-09-03 09:00:00', 'First reset'],
        ['2026-09-20 14:00:00', 'Second reset'],
    ] as [$resetAt, $note]) {
        $this->travelTo(\Carbon\Carbon::parse($resetAt, 'Asia/Baghdad'));
        Queue::updateOrCreate(['tanker_id' => $this->tanker->id], [
            'gatekeeper_id' => $this->gatekeeper->id,
            'status' => 'green',
            'note' => $note,
        ]);

        $this->actingAs($this->gatekeeper)
            ->post(route('gatekeeper.reset'))
            ->assertOk();
    }

    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.history', [
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-30',
        ]))
        ->assertOk()
        ->assertSee('First reset')
        ->assertSee('Second reset')
        ->assertSee('لە 2026-09-01 تا 2026-09-30');

    expect(QueueArchive::count())->toBe(2)
        ->and(QueueArchiveItem::where('tanker_id', $this->tanker->id)->count())->toBe(2);
});

it('orders the monthly report numerically by ranking', function () {
    foreach ([10, 2] as $ranking) {
        $tanker = Tanker::create([
            'sequence_number' => (string) $ranking,
            'sequence_owner' => 'Owner '.$ranking,
            'plate_number' => 'RANK-'.$ranking,
            'truck_type' => 'Tanker',
        ]);
        Queue::create([
            'tanker_id' => $tanker->id,
            'gatekeeper_id' => $this->gatekeeper->id,
            'status' => 'green',
        ]);
    }
    Queue::create([
        'tanker_id' => $this->tanker->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'green',
    ]);

    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.history', ['month' => now('Asia/Baghdad')->format('Y-m'), 'status' => 'green']))
        ->assertOk()
        ->assertSeeInOrder(['RANK-2', 'RANK-10', 'TEST-100']);
});
