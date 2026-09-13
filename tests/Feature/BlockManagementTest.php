<?php

use App\Models\Driver;
use App\Models\Tanker;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');
    $this->driver = Driver::query()->create([
        'name' => 'Blocked Driver',
        'phone' => '7701234567',
        'license_number' => 'LICENSE-1',
        'has_certificate' => false,
    ]);
    $this->tanker = Tanker::query()->create([
        'sequence_number' => '17',
        'sequence_owner' => 'Owner',
        'plate_number' => 'BLOCK-17',
        'truck_type' => 'MAN',
        'driver_id' => $this->driver->id,
    ]);
});

it('blocks and unblocks drivers while keeping their record', function () {
    $this->actingAs($this->user)
        ->patch(route('drivers.block', $this->driver))
        ->assertRedirect();

    expect($this->driver->fresh()->blocked_at)->not->toBeNull();

    $this->get(route('drivers.index'))
        ->assertOk()
        ->assertSee('Blocked Driver')
        ->assertSee('blocked-row', false)
        ->assertSee('شۆفێر بلۆککراوە')
        ->assertSee(route('drivers.unblock', $this->driver), false);

    $this->getJson(route('gatekeeper.sync.snapshot'))
        ->assertOk()
        ->assertJsonMissingPath('tankers.0.driver');
    $this->get(route('blocks.index'))
        ->assertOk()
        ->assertSee('Blocked Driver')
        ->assertSee('data-block-search', false)
        ->assertSee("selectTab('drivers')", false)
        ->assertSee("selectTab('tankers')", false)
        ->assertSee("activeTab === 'drivers'", false)
        ->assertSee("activeTab === 'tankers'", false);

    $this->delete(route('drivers.unblock', $this->driver))->assertRedirect();

    expect($this->driver->fresh()->blocked_at)->toBeNull();
});

it('keeps blocked tankers visible in the gatekeeper snapshot and original page', function () {
    $originalSequence = $this->tanker->sequence_number;

    $this->actingAs($this->user)
        ->patch(route('tankers.block', $this->tanker))
        ->assertRedirect();

    expect($this->tanker->fresh()->blocked_at)->not->toBeNull()
        ->and($this->tanker->fresh()->sequence_number)->toBe($originalSequence);

    $this->get(route('tankers.index'))
        ->assertOk()
        ->assertSee('BLOCK-17')
        ->assertSee('blocked-row', false)
        ->assertSee("actionsTanker.id + '/block'", false)
        ->assertSee('لابردنی بلۆک');

    $this->getJson(route('gatekeeper.sync.snapshot'))
        ->assertOk()
        ->assertJsonPath('tankers.0.plate_number', 'BLOCK-17')
        ->assertJsonPath('tankers.0.blocked_at', fn ($value) => filled($value));

    $this->get(route('blocks.index'))->assertSee('BLOCK-17');
});

it('keeps driver blocks independent from tanker and gatekeeper pages', function () {
    $this->driver->update(['blocked_at' => now()]);

    $this->actingAs($this->user)
        ->getJson(route('gatekeeper.sync.snapshot'))
        ->assertOk()
        ->assertJsonPath('tankers.0.plate_number', 'BLOCK-17')
        ->assertJsonMissingPath('tankers.0.driver');

    $this->get(route('tankers.index'))
        ->assertOk()
        ->assertSee('BLOCK-17')
        ->assertDontSee('شۆفێر بلۆککراوە');
});

it('keeps block mutations behind edit permissions', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['view drivers', 'view tankers']);

    $this->actingAs($viewer)
        ->patch(route('drivers.block', $this->driver))
        ->assertForbidden();

    $this->patch(route('tankers.block', $this->tanker))->assertForbidden();
});
