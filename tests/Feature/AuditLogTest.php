<?php

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create([
        'name' => 'Audit Admin',
        'email' => 'audit-admin@example.com',
    ]);
    $this->admin->assignRole('super_admin');
});

it('does not record ordinary page views or searches', function () {
    $this->actingAs($this->admin)
        ->get(route('tankers.index', ['search' => 'TEST']))
        ->assertOk();

    expect(AuditLog::query()->count())->toBe(0);
});

it('redacts passwords and security tokens from recorded request data', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Audited User',
            'email' => 'audited@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'employee',
        ])
        ->assertRedirect();

    $log = AuditLog::query()->where('route_name', 'users.store')->firstOrFail();

    expect($log->request_data['name'])->toBe('Audited User')
        ->and($log->request_data['password'])->toBe('[REDACTED]')
        ->and($log->request_data['password_confirmation'])->toBe('[REDACTED]');
});

it('records forbidden operations attempted by authenticated users', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('view tankers');

    $this->actingAs($viewer)
        ->post(route('tankers.store'), [])
        ->assertForbidden();

    $log = AuditLog::query()->where('user_id', $viewer->id)->latest('id')->firstOrFail();

    expect($log->route_name)->toBe('tankers.store')
        ->and($log->status_code)->toBe(403)
        ->and($log->humanDescription())->toContain('ڕێگەی پێنەدرا');
});

it('records successful login and logout operations', function () {
    $this->post(route('login.attempt'), [
        'email' => $this->admin->email,
        'password' => 'password',
    ])->assertRedirect();

    expect(AuditLog::query()
        ->where('user_id', $this->admin->id)
        ->where('route_name', 'login.attempt')
        ->exists())->toBeTrue();

    $this->post(route('logout'))->assertRedirect('/login');

    expect(AuditLog::query()
        ->where('user_id', $this->admin->id)
        ->where('route_name', 'logout')
        ->exists())->toBeTrue();
});

it('restricts the audit viewer to users with audit permission', function () {
    $employee = User::factory()->create();
    $employee->assignRole('employee');

    $this->actingAs($employee)
        ->get(route('audit-logs.index'))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('audit-logs.index'))
        ->assertOk()
        ->assertSee('تۆماری کردەوەکانی بەکارهێنەران')
        ->assertSee('چی کردووە؟')
        ->assertSee('flex shrink-0 flex-row flex-nowrap gap-3', false)
        ->assertDontSee('<th class="px-4 py-3">ڕێڕەو</th>', false);
});

it('displays and filters audit times using Sulaimaniyah local time', function () {
    AuditLog::query()->create([
        'user_id' => $this->admin->id,
        'user_name' => $this->admin->name,
        'user_email' => $this->admin->email,
        'method' => 'POST',
        'route_name' => 'drivers.store',
        'path' => '/drivers',
        'request_data' => ['name' => 'Timezone Driver'],
        'status_code' => 302,
        'created_at' => Carbon::parse('2026-09-01 21:30:00', 'UTC'),
    ]);

    $this->actingAs($this->admin)
        ->get(route('audit-logs.index', ['from' => '2026-09-02', 'to' => '2026-09-02']))
        ->assertOk()
        ->assertSee('کاتی سلێمانی')
        ->assertSee('2026-09-02')
        ->assertSee('00:30:00')
        ->assertSee('Timezone Driver');
});
