<?php

use App\Models\Driver;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');

    Driver::query()->create([
        'name' => 'Realtime Search Driver',
        'phone' => '7701234567',
        'license_number' => 'LICENSE-987',
        'has_certificate' => true,
        'certificate_number' => 'CERTIFICATE-654',
    ]);

    Driver::query()->create([
        'name' => 'Unrelated Driver',
        'phone' => '7500000000',
        'license_number' => 'OTHER-LICENSE',
        'has_certificate' => false,
        'certificate_number' => null,
    ]);
});

it('searches every requested driver field on the server fallback', function (string $search) {
    $this->actingAs($this->user)
        ->get(route('drivers.index', ['search' => $search]))
        ->assertOk()
        ->assertSee('Realtime Search Driver')
        ->assertDontSee('Unrelated Driver');
})->with([
    'name' => 'Realtime Search',
    'mobile number' => '7701234567',
    'license number' => 'LICENSE-987',
    'certificate number' => 'CERTIFICATE-654',
]);

it('renders the realtime client-side search data and input handler', function () {
    $this->actingAs($this->user)
        ->get(route('drivers.index'))
        ->assertOk()
        ->assertSee('@input.debounce.100ms="filterDrivers()"', false)
        ->assertSee('data-driver-search="Realtime Search Driver 7701234567 LICENSE-987 CERTIFICATE-654"', false);
});
