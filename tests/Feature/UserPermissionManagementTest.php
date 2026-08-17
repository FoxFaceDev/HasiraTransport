<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');
});

it('lets a super admin open the user and role management pages', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('users.index'))
        ->assertOk();

    $this->actingAs($this->superAdmin)
        ->get(route('roles.index'))
        ->assertOk();
});

it('creates a role with the selected checkbox permissions', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('roles.store'), [
            'name' => 'dispatcher',
            'permissions' => ['view tankers', 'view reports'],
        ])
        ->assertSessionHasNoErrors();

    $role = Role::findByName('dispatcher');

    expect($role->hasPermissionTo('view tankers'))->toBeTrue()
        ->and($role->hasPermissionTo('view reports'))->toBeTrue()
        ->and($role->hasPermissionTo('delete tankers'))->toBeFalse();
});

it('creates a user and assigns the selected role', function () {
    Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);

    $this->actingAs($this->superAdmin)
        ->post(route('users.store'), [
            'name' => 'Test Dispatcher',
            'email' => 'dispatcher@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'dispatcher',
        ])
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'dispatcher@example.com')->firstOrFail()->hasRole('dispatcher'))->toBeTrue();
});

it('enforces granular permissions on application routes', function () {
    $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
    $role->givePermissionTo('view tankers');

    $viewer = User::factory()->create();
    $viewer->assignRole($role);

    $this->actingAs($viewer)
        ->get(route('tankers.index'))
        ->assertOk();

    $this->actingAs($viewer)
        ->get(route('users.index'))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->get(route('roles.index'))
        ->assertForbidden();
});

it('protects the final super admin account', function () {
    $otherUser = User::factory()->create();
    $otherUser->givePermissionTo('manage users');

    $this->actingAs($otherUser)
        ->delete(route('users.destroy', $this->superAdmin))
        ->assertSessionHasErrors('user');

    expect($this->superAdmin->fresh())->not->toBeNull();
});
