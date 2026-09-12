<?php

use App\Models\Tanker;
use App\Models\TankerTransfer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');
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
    ]);

    $this->actingAs($this->user)
        ->get(route('tankers.index'))
        ->assertOk()
        ->assertSee('خەتەکان')
        ->assertSee('ڕیزبەندی')
        ->assertSee('خاوەنی ڕیزبەندی')
        ->assertSee('7707654321')
        ->assertDontSee('ناوی شۆفێر')
        ->assertDontSee('خاوەنی زنجیرە')
        ->assertDontSee('<th class="py-4 px-6 font-normal">#</th>', false)
        ->assertSee('@input.debounce.100ms="filterTankers()"', false)
        ->assertSee('flex flex-nowrap items-center gap-2 whitespace-nowrap', false)
        ->assertSee("localStorage.getItem('sidebar-collapsed')", false)
        ->assertSee('@click="toggleSidebar()"', false)
        ->assertSee("sidebarCollapsed ? 'is-collapsed w-20' : 'w-64'", false)
        ->assertSee('data-tanker-search="1 خاوەنی تاقیکردنەوە 7707654321 TEST-PLATE-1 TEST-VIN-1 MAN 2020 سپی"', false);
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
        ])
        ->assertSessionHasNoErrors();

    $newTanker = Tanker::query()->where('plate_number', 'TEST-PLATE-2')->firstOrFail();

    expect(Tanker::query()->where('vin', 'SHARED-VIN')->count())->toBe(2)
        ->and($newTanker->sequence_owner_phone)->toBe('7702222222')
        ->and($newTanker->driver_id)->toBeNull();
});

it('sells a line while retaining every owner and truck in the transfer chain', function () {
    $tanker = Tanker::query()->create([
        'sequence_number' => '15',
        'sequence_owner' => 'First Owner',
        'sequence_owner_phone' => '07500000001',
        'plate_number' => 'OLD-PLATE',
        'vin' => 'OLD-VIN',
        'truck_type' => 'Old Truck',
        'truck_color' => 'White',
    ]);

    $this->actingAs($this->user)->post(route('tankers.sell', $tanker), [
        'new_owner' => 'Second Owner',
        'new_owner_phone' => '07500000002',
        'new_plate_number' => 'NEW-PLATE',
        'new_vin' => 'NEW-VIN',
        'new_truck_type' => 'New Truck',
        'new_truck_color' => 'Blue',
        'transferred_at' => '2026-09-10',
        'note' => 'First sale',
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->user)->post(route('tankers.sell', $tanker), [
        'new_owner' => 'Third Owner',
        'new_owner_phone' => '07500000003',
        'new_plate_number' => 'NEW-PLATE',
        'new_vin' => 'NEW-VIN',
        'new_truck_type' => 'New Truck',
        'new_truck_color' => 'Blue',
        'transferred_at' => '2026-09-11',
    ])->assertSessionHasNoErrors();

    $tanker->refresh();
    $transfers = TankerTransfer::query()->orderBy('id')->get();

    expect($tanker->sequence_owner)->toBe('Third Owner')
        ->and($tanker->plate_number)->toBe('NEW-PLATE')
        ->and($transfers)->toHaveCount(2)
        ->and($transfers[0]->previous_owner)->toBe('First Owner')
        ->and($transfers[0]->new_owner)->toBe('Second Owner')
        ->and($transfers[0]->previous_plate_number)->toBe('OLD-PLATE')
        ->and($transfers[0]->new_plate_number)->toBe('NEW-PLATE')
        ->and($transfers[1]->previous_owner)->toBe('Second Owner')
        ->and($transfers[1]->new_owner)->toBe('Third Owner');

    $this->actingAs($this->user)
        ->get(route('tankers.index'))
        ->assertOk()
        ->assertSee('فرۆشتن')
        ->assertSee('مێژووی خاوەندارێتی خەت');
});
