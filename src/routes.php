<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;

Route::get('export-route', function () {
    // dd(config());
    $value = config('MasterConfig.main.APP_CODE');
    echo 'Hello from the Export package!' . json_encode($value);
});

Route::middleware(['web','auth'])->group(function () {
    Route::get('report/list', [Bangsamu\ExportRunner\Controllers\ReportController::class, 'list'])->name('report.list');
    Route::delete('report/list/{id}', [Bangsamu\ExportRunner\Controllers\ReportController::class, 'destroy'])->name('report.list.destroy'); // Handle delete

    // Route::get('/profile', [Bangsamu\Master\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    // Route::patch('/profile', [Bangsamu\Master\Controllers\ProfileController::class, 'update'])->name('profile.update');
    // Route::delete('/profile', [Bangsamu\Master\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
});

