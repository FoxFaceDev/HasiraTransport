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
        ->assertSee('getBlockReason(tanker)', false)
        ->assertSee('blocked-badge', false)
        ->assertDontSee('<th class="py-3 px-4 font-normal">#</th>', false)
        ->assertDontSee('x-text="index + 1"', false);
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

it('reverts a status by clicking its selected action again without showing another button', function () {
    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.index'))
        ->assertOk()
        ->assertSee("getStatus(tanker) === 'green' ? revertStatus(tanker.id)", false)
        ->assertSee("getStatus(tanker) === 'yellow' ? revertStatus(tanker.id)", false)
        ->assertSee("getStatus(tanker) === 'red' ? revertStatus(tanker.id)", false)
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
})->with(['green', 'yellow', 'red']);

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
        ->get(route('gatekeeper.history', ['month' => now('Asia/Baghdad')->format('Y-m'), 'status' => 'green']))
        ->assertOk()
        ->assertSee('لیستی ئێستا و مێژووی مانگ')
        ->assertSee('TEST-100')
        ->assertSee('2020')
        ->assertSee('Current live status');

    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.history', ['month' => '2025-01', 'status' => 'green']))
        ->assertOk()
        ->assertDontSee('TEST-100')
        ->assertDontSee('لیستی ئێستا و مێژووی مانگ');
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
