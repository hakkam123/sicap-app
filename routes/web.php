<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\ConsumeController;
use App\Http\Controllers\ConsumeImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportLogController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\MappingController;
use App\Http\Controllers\PartNumberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Consume Unified & Actions
    Route::get('/consume', [ConsumeController::class, 'index'])->name('consume.index');
    Route::post('/consume', [ConsumeController::class, 'store'])->name('consume.store');
    Route::get('/consume/machines-by-area', [ConsumeController::class, 'getMachinesByArea'])->name('consume.machines-by-area');
    Route::get('/consume/create', [ConsumeController::class, 'create'])->name('consume.create');
    Route::delete('/consume/{consume}', [ConsumeController::class, 'destroy'])->name('consume.destroy');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('areas', AreaController::class);
    Route::resource('machines', MachineController::class);
    Route::resource('part-numbers', PartNumberController::class);

    // Mapping Part <-> Area & Machine (Unified)
    Route::get('/mapping', [MappingController::class, 'index'])->name('mapping.index');
    Route::get('/mapping/{partNumber}/detail', [MappingController::class, 'getPartDetail'])->name('mapping.detail');
    Route::post('/mapping/sync', [MappingController::class, 'sync'])->name('mapping.sync');
    Route::post('/mapping/import', [MappingController::class, 'importMapping'])->name('mapping.import');
    Route::get('/mapping/template', [MappingController::class, 'downloadTemplate'])->name('mapping.template');

    // Import Excel Consume & Logs
    Route::get('/consume/import', [ConsumeImportController::class, 'showForm'])->name('consume.import');
    Route::post('/consume/import', [ConsumeImportController::class, 'store'])->name('consume.import.store');
    Route::get('/consume/template', [ConsumeImportController::class, 'downloadTemplate'])->name('consume.template');
    Route::get('/import-logs', [ImportLogController::class, 'index'])->name('import-logs.index');
    Route::get('/import-logs/{importLog}', [ImportLogController::class, 'show'])->name('import-logs.show');

    // User Management
    Route::resource('users', UserController::class)->except(['show']);
});

require __DIR__.'/auth.php';
