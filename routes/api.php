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

    // ── Sectores module ───────────────────────────────────────────────────────
    Route::prefix('sectores')->name('api.sectores.')->group(function () {
        Route::get('/', [App\Http\Controllers\Sector\SectorController::class, 'index'])
            ->middleware('permission:sectores:list')
            ->name('index');

        Route::post('/', [App\Http\Controllers\Sector\SectorController::class, 'store'])
            ->middleware('permission:sectores:create')
            ->name('store');

        Route::get('/{sectore}', [App\Http\Controllers\Sector\SectorController::class, 'show'])
            ->middleware('permission:sectores:view')
            ->name('show');

        Route::put('/{sectore}', [App\Http\Controllers\Sector\SectorController::class, 'update'])
            ->middleware('permission:sectores:edit')
            ->name('update');

        Route::delete('/{sectore}', [App\Http\Controllers\Sector\SectorController::class, 'destroy'])
            ->middleware('permission:sectores:delete')
            ->name('destroy');
    });

    // ── Personas module ───────────────────────────────────────────────────────
    Route::apiResource('personas', App\Http\Controllers\Sector\PersonaController::class)
        ->names('api.personas')
        ->middleware([
            'index' => 'permission:personas:list',
            'store' => 'permission:personas:create',
            'show' => 'permission:personas:view',
            'update' => 'permission:personas:edit',
            'destroy' => 'permission:personas:delete',
        ]);

    // ── Cargos module ─────────────────────────────────────────────────────────
    Route::apiResource('cargos', App\Http\Controllers\Sector\CargoController::class)
        ->names('api.cargos')
        ->middleware([
            'index' => 'permission:cargos:list',
            'store' => 'permission:cargos:create',
            'show' => 'permission:cargos:view',
            'update' => 'permission:cargos:edit',
            'destroy' => 'permission:cargos:delete',
        ]);

    // ── Bases module ──────────────────────────────────────────────────────────
    Route::prefix('bases')->name('api.bases.')->group(function () {
        Route::get('/', [App\Http\Controllers\Sector\BaseController::class, 'index'])
            ->middleware('permission:bases:list')
            ->name('index');

        Route::post('/', [App\Http\Controllers\Sector\BaseController::class, 'store'])
            ->middleware('permission:bases:create')
            ->name('store');

        Route::get('/{base}', [App\Http\Controllers\Sector\BaseController::class, 'show'])
            ->middleware('permission:bases:view')
            ->name('show');

        Route::put('/{base}', [App\Http\Controllers\Sector\BaseController::class, 'update'])
            ->middleware('permission:bases:edit')
            ->name('update');

        Route::delete('/{base}', [App\Http\Controllers\Sector\BaseController::class, 'destroy'])
            ->middleware('permission:bases:delete')
            ->name('destroy');

        // ── Personal de una base (rutas anidadas) ─────────────────────────────
        Route::prefix('/{base}/personal')->name('personal.')->middleware('permission:bases:view')->group(function () {
            Route::get('/', [App\Http\Controllers\Sector\BasePersonalController::class, 'index'])
                ->name('index');

            Route::post('/', [App\Http\Controllers\Sector\BasePersonalController::class, 'store'])
                ->middleware('permission:bases:edit')
                ->name('store');

            Route::put('/{asignacion}', [App\Http\Controllers\Sector\BasePersonalController::class, 'update'])
                ->middleware('permission:bases:edit')
                ->name('update');

            Route::delete('/{asignacion}', [App\Http\Controllers\Sector\BasePersonalController::class, 'destroy'])
                ->middleware('permission:bases:edit')
                ->name('destroy');
        });
    });
});
