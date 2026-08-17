<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $roleIds = [];

        foreach (['super_admin', 'employee', 'gatekeeper'] as $roleName) {
            DB::table('roles')->insertOrIgnore([
                'name' => $roleName,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $roleIds[$roleName] = DB::table('roles')
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');
        }

        DB::table('users')->select(['id', 'role'])->orderBy('id')->each(function ($user) use ($roleIds) {
            $roleName = array_key_exists($user->role, $roleIds) ? $user->role : 'employee';

            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $roleIds[$roleName],
                'model_type' => User::class,
                'model_id' => $user->id,
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('employee');
        });

        $roles = DB::table('roles')->pluck('name', 'id');

        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->orderBy('model_id')
            ->each(function ($assignment) use ($roles) {
                DB::table('users')->where('id', $assignment->model_id)->update([
                    'role' => $roles[$assignment->role_id] ?? 'employee',
                ]);
            });
    }
};
