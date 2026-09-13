<?php

use App\Models\Tanker;
use App\Models\TankerTransfer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');
});

it('shows the tanker page and all transfer operation controls', function () {
    Tanker::query()->create([
        'sequence_number' => '1',
        'sequence_owner' => 'خاوەنی تاقیکردنەوە',
        'sequence_owner_phone' => '7707654321',
        'plate_number' => 'TEST-PLATE-1',
        'vin' => 'TEST-VIN-1',
        'truck_type' => 'MAN',
        'truck_model' => '2020',
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
        ->assertSee('actionsTanker =', false)
        ->assertSee('showActionsModal = true', false)
        ->assertSee('کردارەکان')
        ->assertDontSee('دوای تۆمارکردن، بەڵگەنامەی A4 بە هەمان دیزاینی نموونەکە دەکرێتەوە بۆ چاپکردن.')
        ->assertSee("localStorage.getItem('sidebar-collapsed')", false)
        ->assertSee('@click="toggleSidebar()"', false)
        ->assertSee("sidebarCollapsed ? 'is-collapsed w-20' : 'w-64'", false)
        ->assertSee('data-tanker-search="1 خاوەنی تاقیکردنەوە 7707654321 TEST-PLATE-1 TEST-VIN-1 MAN 2020 سپی"', false)
        ->assertSee('فرۆشتنی خەت لەگەڵ هەمان بارهەڵگر')
        ->assertSee('فرۆشتنی خەت تەنها و گۆڕینی بارهەڵگر')
        ->assertSee('ژمارەی بەڵگەنامە (No)')
        ->assertSee('گۆڕینی بارهەڵگر - خاوەن وەک خۆی دەمێنێتەوە');
});

it('stores the owner phone and truck model and permits workbook-compatible duplicate vins', function () {
    Tanker::query()->create([
        'sequence_number' => '1',
        'sequence_owner' => 'First Owner',
        'sequence_owner_phone' => '7701111111',
        'plate_number' => 'TEST-PLATE-1',
        'vin' => 'SHARED-VIN',
        'truck_type' => 'MAN',
        'truck_model' => '2020',
        'truck_color' => 'White',
    ]);

    $this->actingAs($this->user)
        ->post(route('tankers.store'), [
            'sequence_number' => '2',
            'sequence_owner' => 'Second Owner',
            'sequence_owner_phone' => '7702222222',
            'plate_number' => 'TEST-PLATE-2',
            'vin' => 'SHARED-VIN',
            'truck_type' => 'MAN',
            'truck_model' => '2021',
            'truck_color' => 'Blue',
        ])
        ->assertSessionHasNoErrors();

    $newTanker = Tanker::query()->where('plate_number', 'TEST-PLATE-2')->firstOrFail();

    expect(Tanker::query()->where('vin', 'SHARED-VIN')->count())->toBe(2)
        ->and($newTanker->sequence_owner_phone)->toBe('7702222222')
        ->and($newTanker->truck_model)->toBe('2021')
        ->and($newTanker->driver_id)->toBeNull();
});

it('supports both sale types and creates their printable documents', function () {
    Storage::fake('public');

    $tanker = Tanker::query()->create([
        'sequence_number' => '15',
        'sequence_owner' => 'First Owner',
        'sequence_owner_phone' => '07500000001',
        'plate_number' => 'OLD-PLATE',
        'vin' => 'OLD-VIN',
        'truck_type' => 'Old Truck',
        'truck_model' => '2018',
        'truck_color' => 'White',
    ]);

    $this->actingAs($this->user)->post(route('tankers.sell', $tanker), [
        'operation_type' => 'sale_with_truck',
        'document_number' => 'DOC-1001',
        'new_owner' => 'Second Owner',
        'new_owner_phone' => '07500000002',
        'transferred_at' => '2026-09-10',
        'buyer_national_id' => 'BUYER-ID-1',
        'buyer_security_code' => 'SEC-1',
        'buyer_agent' => 'Buyer Agent',
        'buyer_agency_number' => 'AGENCY-1',
        'buyer_document_date' => '2026-09-09',
        'note' => 'First sale',
    ])->assertOk()->assertHeader('content-type', 'application/pdf');

    $tanker->refresh();

    expect($tanker->sequence_owner)->toBe('Second Owner')
        ->and($tanker->plate_number)->toBe('OLD-PLATE')
        ->and($tanker->vin)->toBe('OLD-VIN')
        ->and($tanker->truck_model)->toBe('2018');

    $this->actingAs($this->user)->post(route('tankers.sell', $tanker), [
        'operation_type' => 'sale_line_only',
        'document_number' => 'DOC-1002',
        'new_owner' => 'Third Owner',
        'new_owner_phone' => '07500000003',
        'new_plate_number' => 'NEW-PLATE',
        'new_vin' => 'NEW-VIN',
        'new_truck_type' => 'New Truck',
        'new_truck_model' => '2026',
        'new_truck_color' => 'Blue',
        'transferred_at' => '2026-09-11',
    ])->assertOk()->assertHeader('content-type', 'application/pdf');

    $tanker->refresh();
    $transfers = TankerTransfer::query()->orderBy('id')->get();

    expect($tanker->sequence_owner)->toBe('Third Owner')
        ->and($tanker->plate_number)->toBe('NEW-PLATE')
        ->and($tanker->truck_model)->toBe('2026')
        ->and($transfers)->toHaveCount(2)
        ->and($transfers[0]->change_type)->toBe('sale_with_truck')
        ->and($transfers[0]->document_number)->toBe('DOC-1001')
        ->and($transfers[0]->previous_owner)->toBe('First Owner')
        ->and($transfers[0]->new_owner)->toBe('Second Owner')
        ->and($transfers[0]->previous_plate_number)->toBe('OLD-PLATE')
        ->and($transfers[0]->new_plate_number)->toBe('OLD-PLATE')
        ->and($transfers[0]->buyer_national_id)->toBe('BUYER-ID-1')
        ->and($transfers[0]->document_path)->not->toBeNull()
        ->and($transfers[1]->change_type)->toBe('sale_line_only')
        ->and($transfers[1]->document_number)->toBe('DOC-1002')
        ->and($transfers[1]->previous_owner)->toBe('Second Owner')
        ->and($transfers[1]->new_owner)->toBe('Third Owner')
        ->and($transfers[1]->previous_plate_number)->toBe('OLD-PLATE')
        ->and($transfers[1]->new_plate_number)->toBe('NEW-PLATE')
        ->and(Storage::disk('public')->exists($transfers[0]->document_path))->toBeTrue()
        ->and(Storage::disk('public')->exists($transfers[1]->document_path))->toBeTrue();

    $this->actingAs($this->user)
        ->get(route('tankers.transfers.document', [$tanker, $transfers[1]]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($this->user)
        ->get(route('tankers.index'))
        ->assertOk()
        ->assertSee('کردنەوەی بەڵگەنامەی چاپ')
        ->assertSee('مێژووی خاوەندارێتی خەت');
});

it('changes only the truck while keeping the current line owner', function () {
    Storage::fake('public');

    $tanker = Tanker::query()->create([
        'sequence_number' => '21',
        'sequence_owner' => 'Same Owner',
        'sequence_owner_phone' => '07500000021',
        'plate_number' => 'TRUCK-OLD',
        'vin' => 'VIN-OLD',
        'truck_type' => 'Volvo',
        'truck_model' => '2017',
        'truck_color' => 'White',
    ]);

    $this->actingAs($this->user)->post(route('tankers.sell', $tanker), [
        'operation_type' => 'truck_change',
        'document_number' => 'DOC-2001',
        'new_plate_number' => 'TRUCK-NEW',
        'new_vin' => 'VIN-NEW',
        'new_truck_type' => 'MAN',
        'new_truck_model' => '2026',
        'new_truck_color' => 'Blue',
        'transferred_at' => '2026-09-12',
    ])->assertOk()->assertHeader('content-type', 'application/pdf');

    $tanker->refresh();
    $transfer = TankerTransfer::query()->firstOrFail();

    expect($tanker->sequence_owner)->toBe('Same Owner')
        ->and($tanker->sequence_owner_phone)->toBe('07500000021')
        ->and($tanker->plate_number)->toBe('TRUCK-NEW')
        ->and($tanker->vin)->toBe('VIN-NEW')
        ->and($tanker->truck_type)->toBe('MAN')
        ->and($tanker->truck_model)->toBe('2026')
        ->and($tanker->truck_color)->toBe('Blue')
        ->and($transfer->change_type)->toBe('truck_change')
        ->and($transfer->document_number)->toBe('DOC-2001')
        ->and($transfer->previous_truck_model)->toBe('2017')
        ->and($transfer->new_truck_model)->toBe('2026')
        ->and(Storage::disk('public')->exists($transfer->document_path))->toBeTrue();
});

it('requires all new truck details and buyer phone for a line-only sale', function () {
    $tanker = Tanker::query()->create([
        'sequence_number' => '30',
        'sequence_owner' => 'Seller',
        'plate_number' => 'VALIDATION-OLD',
        'truck_type' => 'Old Truck',
    ]);

    $this->actingAs($this->user)->post(route('tankers.sell', $tanker), [
        'operation_type' => 'sale_line_only',
        'new_owner' => 'Buyer',
        'transferred_at' => '2026-09-13',
    ])->assertSessionHasErrors([
        'document_number',
        'new_owner_phone',
        'new_plate_number',
        'new_vin',
        'new_truck_type',
        'new_truck_model',
        'new_truck_color',
    ]);

    expect(TankerTransfer::query()->count())->toBe(0);
});
