<?php

use App\Models\GatekeeperSyncOperation;
use App\Models\Queue;
use App\Models\Tanker;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ->assertJsonPath('tankers.0.sequence_owner_phone', '07501112233')
        ->assertJsonMissingPath('tankers.0.driver')
        ->assertJsonPath('tankers.0.queue.status', 'pending');
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
