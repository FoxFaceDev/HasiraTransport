<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        PermissionCatalog::names()->each(fn (string $name) => Permission::findOrCreate($name, 'web'));

        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $employee = Role::findOrCreate('employee', 'web');
        $gatekeeper = Role::findOrCreate('gatekeeper', 'web');

        $superAdmin->syncPermissions(Permission::query()->where('guard_name', 'web')->get());
        $employee->syncPermissions([
            'view drivers', 'create drivers', 'edit drivers', 'delete drivers',
            'view tankers', 'create tankers', 'edit tankers', 'delete tankers',
        ]);
        $gatekeeper->syncPermissions([
            'view gatekeeper', 'update queue status', 'update queue notes',
            'update gatekeeper phone', 'block tankers from gatekeeper', 'reset queue',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
