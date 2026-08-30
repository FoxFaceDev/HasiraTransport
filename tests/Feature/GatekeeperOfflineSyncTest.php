<?php

use App\Models\Driver;
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

    $driver = Driver::create([
        'name' => 'Offline Driver',
        'phone' => '07500000000',
        'license_number' => 'LIC-1',
        'has_certificate' => true,
    ]);

    $this->tanker = Tanker::create([
        'driver_id' => $driver->id,
        'sequence_number' => '12',
        'sequence_owner' => 'Owner',
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
        ->assertJsonPath('tankers.0.driver.name', 'Offline Driver')
        ->assertJsonPath('tankers.0.queue.status', 'pending');
});

it('embeds the initial truck snapshot in the gatekeeper page', function () {
    $this->actingAs($this->gatekeeper)
        ->get(route('gatekeeper.index'))
        ->assertOk()
        ->assertSee('gatekeeperQueueManager', false)
        ->assertSee('TEST-100', false)
        ->assertSee('Offline Driver', false)
        ->assertDontSee('<th class="py-3 px-4 font-normal">#</th>', false)
        ->assertDontSee('x-text="index + 1"', false);
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
