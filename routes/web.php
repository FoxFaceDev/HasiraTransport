<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\TankerController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        if (auth()->user()->can('view tankers')) {
            return redirect()->route('tankers.index');
        }

        if (auth()->user()->can('view gatekeeper')) {
            return redirect()->route('gatekeeper.index');
        }

        if (auth()->user()->can('manage users')) {
            return redirect()->route('users.index');
        }

        abort(403, 'No dashboard permission has been assigned to this account.');
    })->name('dashboard');

    Route::get('/drivers', [DriverController::class, 'index'])->middleware('can:view drivers')->name('drivers.index');
    Route::post('/drivers', [DriverController::class, 'store'])->middleware('can:create drivers')->name('drivers.store');
    Route::put('/drivers/{driver}', [DriverController::class, 'update'])->middleware('can:edit drivers')->name('drivers.update');
    Route::delete('/drivers/{driver}', [DriverController::class, 'destroy'])->middleware('can:delete drivers')->name('drivers.destroy');

    Route::get('/tankers', [TankerController::class, 'index'])->middleware('can:view tankers')->name('tankers.index');
    Route::post('/tankers', [TankerController::class, 'store'])->middleware('can:create tankers')->name('tankers.store');
    Route::put('/tankers/{tanker}', [TankerController::class, 'update'])->middleware('can:edit tankers')->name('tankers.update');
    Route::delete('/tankers/{tanker}', [TankerController::class, 'destroy'])->middleware('can:delete tankers')->name('tankers.destroy');
    Route::post('/settings/max-tankers', [TankerController::class, 'updateSetting'])->middleware('can:manage tanker settings')->name('settings.max-tankers');

    Route::get('/gatekeeper', [QueueController::class, 'index'])->middleware('can:view gatekeeper')->name('gatekeeper.index');
    Route::get('/gatekeeper/filter/{status}', [QueueController::class, 'filter'])->middleware('can:view gatekeeper')->name('gatekeeper.filter');
    Route::post('/gatekeeper/queue/{tanker}', [QueueController::class, 'updateStatus'])->middleware('can:update queue status')->name('gatekeeper.update-status');
    Route::post('/gatekeeper/queue/{tanker}/note', [QueueController::class, 'updateNote'])->middleware('can:update queue notes')->name('gatekeeper.update-note');
    Route::get('/gatekeeper/sync', [QueueController::class, 'syncSnapshot'])->middleware('can:view gatekeeper')->name('gatekeeper.sync.snapshot');
    Route::post('/gatekeeper/sync', [QueueController::class, 'syncPush'])->middleware('can:view gatekeeper')->name('gatekeeper.sync.push');
    Route::post('/gatekeeper/reset', [QueueController::class, 'resetQueue'])->middleware('can:reset queue')->name('gatekeeper.reset');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/reports', [ReportController::class, 'index'])->middleware('can:view reports')->name('reports.index');
    Route::get('/reports/{file}/download', [ReportController::class, 'download'])->middleware('can:download reports')->name('reports.download');

    Route::middleware('can:manage users')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::get('/roles', [RoleManagementController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleManagementController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleManagementController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleManagementController::class, 'destroy'])->name('roles.destroy');
    });
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
