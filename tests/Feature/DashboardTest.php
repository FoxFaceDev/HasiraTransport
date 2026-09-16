<?php

use App\Models\Queue;
use App\Models\QueueArchive;
use App\Models\Tanker;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo(\Carbon\Carbon::parse('2026-09-16 15:00:00', 'Asia/Baghdad'));

    $this->gatekeeper = User::factory()->create();
    $this->gatekeeper->assignRole('gatekeeper');
});

it('shows a professional operational dashboard with live and archived truck statistics', function () {
    $departed = Tanker::create([
        'sequence_number' => '10',
        'sequence_owner' => 'Departed owner',
        'plate_number' => 'TODAY-DEPARTED',
        'truck_type' => 'Tanker',
    ]);
    Queue::create([
        'tanker_id' => $departed->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'departed',
        'scheduled_date' => '2026-09-16',
        'scheduled_time' => '12:00 نیوەڕۆ',
        'status_updated_at' => now(),
    ]);

    $scheduled = Tanker::create([
        'sequence_number' => '11',
        'sequence_owner' => 'Scheduled owner',
        'plate_number' => 'TODAY-SCHEDULED',
        'truck_type' => 'Tanker',
    ]);
    Queue::create([
        'tanker_id' => $scheduled->id,
        'gatekeeper_id' => $this->gatekeeper->id,
        'status' => 'yellow',
        'scheduled_date' => '2026-09-16',
        'scheduled_time' => '5:30 بەیانی',
        'status_updated_at' => now()->subHour(),
    ]);

    $archived = Tanker::create([
        'sequence_number' => '12',
        'sequence_owner' => 'Archived owner',
        'plate_number' => 'ARCHIVED-DEPARTED',
        'truck_type' => 'Tanker',
    ]);
    $archive = QueueArchive::create([
        'reset_by' => $this->gatekeeper->id,
        'reset_at' => now()->subHours(2),
    ]);
    $archive->items()->create([
        'tanker_id' => $archived->id,
        'sequence_number' => $archived->sequence_number,
        'sequence_owner' => $archived->sequence_owner,
        'plate_number' => $archived->plate_number,
        'truck_type' => $archived->truck_type,
        'status' => 'departed',
        'scheduled_date' => '2026-09-16',
        'scheduled_time' => '10:00',
        'status_updated_at' => now()->subHours(2),
    ]);

    Tanker::create([
        'sequence_number' => '13',
        'sequence_owner' => 'Blocked owner',
        'plate_number' => 'BLOCKED-PENDING',
        'truck_type' => 'Tanker',
        'blocked_at' => now(),
    ]);

    $this->actingAs($this->gatekeeper)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('داشبۆردی بەڕێوەبردنی هاتوچۆ')
        ->assertSee('ڕۆیشتووەکانی ئەمڕۆ')
        ->assertSee('ڕۆیشتن لە هەفتەی ئێستا')
        ->assertSee('لە شەممە تا هەینی بەپێی ڕۆژ')
        ->assertSeeInOrder(['شەممە', 'یەک شەممە', 'دوو شەممە', 'سێ شەممە', 'چوار شەممە', 'پێنج شەممە', 'هەینی'])
        ->assertSee('پلانی ٧ ڕۆژی داهاتوو')
        ->assertSee('دابەشبوونی دۆخی ئێستا')
        ->assertSee('TODAY-DEPARTED')
        ->assertSee('ARCHIVED-DEPARTED')
        ->assertSee('5:30 بەیانی')
        ->assertSee('12:00 نیوەڕۆ')
        ->assertSee('dashboard-donut', false)
        ->assertSee('dashboard-bar', false)
        ->assertSee(route('dashboard'), false);
});

it('allows tanker viewers to use the dashboard', function () {
    $employee = User::factory()->create();
    $employee->assignRole('employee');

    $this->actingAs($employee)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('هەموو خەتەکان');
});

it('protects truck statistics from users without tanker or gatekeeper access', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage users');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});
