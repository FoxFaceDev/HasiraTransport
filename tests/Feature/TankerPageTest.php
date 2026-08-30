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
        'name' => 'Truck Driver',
        'phone' => '7701234567',
        'license_number' => 'D1234567',
        'has_certificate' => false,
        'certificate_number' => null,
    ]);
});

it('shows the renamed truck page and ranking labels', function () {
    Tanker::query()->create([
        'sequence_number' => '1',
        'sequence_owner' => 'خاوەنی تاقیکردنەوە',
        'sequence_owner_phone' => '7707654321',
        'plate_number' => 'TEST-PLATE-1',
        'vin' => 'TEST-VIN-1',
        'truck_type' => 'MAN 2020',
        'truck_color' => 'سپی',
        'driver_id' => $this->driver->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('tankers.index'))
        ->assertOk()
        ->assertSee('خەتەکان')
        ->assertSee('ڕیزبەندی')
        ->assertSee('خاوەنی ڕیزبەندی')
        ->assertSee('7707654321')
        ->assertDontSee('خاوەنی زنجیرە')
        ->assertSee('@input.debounce.100ms="filterTankers()"', false)
        ->assertSee('data-tanker-search="1 خاوەنی تاقیکردنەوە 7707654321 TEST-PLATE-1 TEST-VIN-1 MAN 2020 سپی Truck Driver 7701234567"', false);
});

it('stores the sequence owner phone and permits workbook-compatible duplicate vins', function () {
    Tanker::query()->create([
        'sequence_number' => '1',
        'sequence_owner' => 'First Owner',
        'sequence_owner_phone' => '7701111111',
        'plate_number' => 'TEST-PLATE-1',
        'vin' => 'SHARED-VIN',
        'truck_type' => 'MAN 2020',
        'truck_color' => 'White',
        'driver_id' => $this->driver->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('tankers.store'), [
            'sequence_number' => '2',
            'sequence_owner' => 'Second Owner',
            'sequence_owner_phone' => '7702222222',
            'plate_number' => 'TEST-PLATE-2',
            'vin' => 'SHARED-VIN',
            'truck_type' => 'MAN 2021',
            'truck_color' => 'Blue',
            'driver_id' => $this->driver->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Tanker::query()->where('vin', 'SHARED-VIN')->count())->toBe(2)
        ->and(Tanker::query()->where('plate_number', 'TEST-PLATE-2')->value('sequence_owner_phone'))->toBe('7702222222');
});
