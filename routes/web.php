<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\ConsumeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportLogController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\MappingController;
use App\Http\Controllers\PartNumberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Dashboard Drill-Down
    Route::get('/dashboard/drill-down', [DashboardController::class, 'drillDown'])->name('dashboard.drill-down');

    // Consume Unified & Actions
    Route::get('/consume', [ConsumeController::class, 'index'])->name('consume.index');
    Route::post('/consume', [ConsumeController::class, 'store'])->name('consume.store');
    Route::put('/consume/{consume}', [ConsumeController::class, 'update'])->name('consume.update');
    Route::post('/consume/import', [ConsumeController::class, 'import'])->name('consume.import');
    Route::get('/consume/template', [ConsumeController::class, 'downloadTemplate'])->name('consume.template');
    Route::post('/consume/sync-api', [ConsumeController::class, 'syncApi'])->name('consume.sync-api');
    Route::post('/consume/sync-schedules', [ConsumeController::class, 'updateSyncSchedules'])->name('consume.sync-schedules.update');
    Route::get('/consume/export', [ConsumeController::class, 'export'])->name('consume.export');
    Route::get('/consume/machines-by-area', [ConsumeController::class, 'getMachinesByArea'])->name('consume.machines-by-area');
    Route::delete('/consume/{consume}', [ConsumeController::class, 'destroy'])->name('consume.destroy');

    // User Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Reports & Export
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    // Area Routes & Import/Template
    Route::post('/areas/import', [AreaController::class, 'import'])->name('areas.import');
    Route::get('/areas/template', [AreaController::class, 'downloadTemplate'])->name('areas.template');
    Route::resource('areas', AreaController::class)->except(['create', 'edit', 'show']);

    // Machine Routes & Import/Template
    Route::post('/machines/import', [MachineController::class, 'import'])->name('machines.import');
    Route::get('/machines/template', [MachineController::class, 'downloadTemplate'])->name('machines.template');
    Route::resource('machines', MachineController::class)->except(['create', 'edit', 'show']);

    // Part Number Routes & Import/Template
    Route::post('/part-numbers/import', [PartNumberController::class, 'import'])->name('part-numbers.import');
    Route::get('/part-numbers/template', [PartNumberController::class, 'downloadTemplate'])->name('part-numbers.template');
    Route::resource('part-numbers', PartNumberController::class)->except(['create', 'edit', 'show']);

    // Mapping Part <-> Area & Machine (Unified)
    Route::get('/mapping', [MappingController::class, 'index'])->name('mapping.index');
    Route::get('/mapping/{partNumber}/detail', [MappingController::class, 'getPartDetail'])->name('mapping.detail');
    Route::post('/mapping/sync', [MappingController::class, 'sync'])->name('mapping.sync');
    Route::post('/mapping/import', [MappingController::class, 'importMapping'])->name('mapping.import');
    Route::post('/mapping-parts/import', [MappingController::class, 'importMapping'])->name('mapping-parts.import');
    Route::get('/mapping/template', [MappingController::class, 'downloadTemplate'])->name('mapping.template');
    Route::get('/mapping-parts/template', [MappingController::class, 'downloadTemplate'])->name('mapping-parts.template');

    // Import Logs
    Route::get('/import-logs', [ImportLogController::class, 'index'])->name('import-logs.index');
    Route::get('/import-logs/{importLog}', [ImportLogController::class, 'show'])->name('import-logs.show');

    // User Management
    Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);
});

require __DIR__.'/auth.php';
