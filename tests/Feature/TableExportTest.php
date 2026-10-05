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
});

it('exports the filtered drivers table as an Excel workbook', function () {
    Driver::query()->create([
        'name' => 'Included Driver',
        'phone' => '7701111111',
        'license_number' => 'LICENSE-1',
        'has_certificate' => true,
        'certificate_number' => 'CERT-1',
    ]);
    Driver::query()->create([
        'name' => 'Excluded Driver',
        'phone' => '7702222222',
        'license_number' => 'LICENSE-2',
        'has_certificate' => false,
    ]);

    $response = $this->actingAs($this->user)->get(route('drivers.export', ['search' => 'Included']));

    $response->assertOk()->assertDownload('drivers-'.now('Asia/Baghdad')->format('Y-m-d').'.xlsx');
    $worksheet = worksheetXml($response);

    expect($worksheet)->toContain('Included Driver')
        ->not->toContain('Excluded Driver');
});

it('exports the filtered tankers table as an Excel workbook', function () {
    foreach (['INCLUDED', 'EXCLUDED'] as $index => $plate) {
        Tanker::query()->create([
            'sequence_number' => (string) ($index + 1),
            'sequence_owner' => $plate.' Owner',
            'sequence_owner_phone' => '770000000'.$index,
            'plate_number' => $plate.'-PLATE',
            'vin' => $plate.'-VIN',
            'truck_type' => 'MAN',
            'truck_model' => '2025',
            'truck_color' => 'White',
        ]);
    }

    $response = $this->actingAs($this->user)->get(route('tankers.export', ['search' => 'INCLUDED']));

    $response->assertOk()->assertDownload('tankers-'.now('Asia/Baghdad')->format('Y-m-d').'.xlsx');
    $worksheet = worksheetXml($response);

    expect($worksheet)->toContain('INCLUDED-PLATE')
        ->not->toContain('EXCLUDED-PLATE');
});

function worksheetXml($response): string
{
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    return $worksheet;
}
