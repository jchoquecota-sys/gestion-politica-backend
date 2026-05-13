<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here are prefixed with /api and protected against unauthenticated
| access via the custom Authenticate middleware (returns 401 JSON, no redirect).
|
*/

// ── Public routes ─────────────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register'])->name('api.register');
Route::post('/login', [AuthController::class, 'login'])->name('api.login');

// ── Protected routes (Sanctum token required) ─────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');

    // ── Roles module ──────────────────────────────────────────────────────────
    // Each route is protected by its own granular permission.
    // The super-admin role bypasses all checks via Gate::before in AppServiceProvider.
    Route::prefix('roles')->name('api.roles.')->group(function () {

        Route::get('/', [RoleController::class, 'index'])
            ->middleware('permission:roles:list')
            ->name('index');

        Route::get('/permissions', [RoleController::class, 'availablePermissions'])
            ->middleware('permission:roles:assign-permissions')
            ->name('permissions');

        Route::get('/{role}', [RoleController::class, 'show'])
            ->middleware('permission:roles:view')
            ->name('show');

        Route::post('/', [RoleController::class, 'store'])
            ->middleware('permission:roles:create')
            ->name('store');

        Route::put('/{role}', [RoleController::class, 'update'])
            ->middleware('permission:roles:edit')
            ->name('update');

        Route::delete('/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:roles:delete')
            ->name('destroy');
    });

    // ── Users module ──────────────────────────────────────────────────────────
    Route::prefix('users')->name('api.users.')->group(function () {

        Route::get('/', [UserController::class, 'index'])
            ->middleware('permission:users:list')
            ->name('index');

        Route::get('/{user}', [UserController::class, 'show'])
            ->middleware('permission:users:view')
            ->name('show');

        Route::post('/', [UserController::class, 'store'])
            ->middleware('permission:users:create')
            ->name('store');

        Route::put('/{user}', [UserController::class, 'update'])
            ->middleware('permission:users:edit')
            ->name('update');

        Route::delete('/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users:delete')
            ->name('destroy');
    });
});
