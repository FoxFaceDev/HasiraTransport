<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->with('roles')->latest()->get(),
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $user->assignRole($validated['role']);

        return back()->with('success', 'بەکارهێنەرەکە بە سەرکەوتوویی زیادکرا.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ]);

        if ($this->wouldRemoveLastSuperAdmin($user, $validated['role'])) {
            return back()->withErrors(['role' => 'ناتوانرێت تاکە سوپەر ئەدمین بگۆڕدرێت.']);
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        return back()->with('success', 'زانیارییەکانی بەکارهێنەر نوێکرایەوە.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['user' => 'ناتوانیت هەژماری خۆت بسڕیتەوە.']);
        }

        if ($this->wouldRemoveLastSuperAdmin($user)) {
            return back()->withErrors(['user' => 'ناتوانرێت تاکە سوپەر ئەدمین بسڕدرێتەوە.']);
        }

        $user->delete();

        return back()->with('success', 'بەکارهێنەرەکە سڕایەوە.');
    }

    private function wouldRemoveLastSuperAdmin(User $user, ?string $newRole = null): bool
    {
        if (! $user->hasRole('super_admin') || $newRole === 'super_admin') {
            return false;
        }

        return User::role('super_admin')->count() <= 1;
    }
}
