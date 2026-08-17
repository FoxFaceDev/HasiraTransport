<?php

namespace App\Http\Controllers;

use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleManagementController extends Controller
{
    public function index(): View
    {
        $permissionGroups = PermissionCatalog::groups();

        return view('roles.index', [
            'roles' => Role::query()
                ->where('guard_name', 'web')
                ->with('permissions')
                ->withCount('users')
                ->orderBy('name')
                ->get(),
            'permissionGroups' => $permissionGroups,
            'permissionLabels' => collect($permissionGroups)->flatMap(fn (array $permissions) => $permissions),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRole($request);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', 'ڕۆڵەکە بە سەرکەوتوویی دروستکرا.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === 'super_admin') {
            return back()->withErrors(['role' => 'ڕۆڵی سوپەر ئەدمین پارێزراوە و ناگۆڕدرێت.']);
        }

        $validated = $this->validateRole($request, $role);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', 'ڕۆڵ و دەسەڵاتەکان نوێکرانەوە.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === 'super_admin') {
            return back()->withErrors(['role' => 'ڕۆڵی سوپەر ئەدمین ناتوانرێت بسڕدرێتەوە.']);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'پێش سڕینەوە، بەکارهێنەرەکانی ئەم ڕۆڵە بگوازەوە بۆ ڕۆڵێکی تر.']);
        }

        $role->delete();

        return back()->with('success', 'ڕۆڵەکە سڕایەوە.');
    }

    /**
     * @return array{name: string, permissions?: array<int, string>}
     */
    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ]);
    }
}
