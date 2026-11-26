<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReportBatchController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('reports', [ReportController::class, 'store']);
    Route::post('report-batches', [ReportBatchController::class, 'store']);
    Route::get('report-batches/{batch}', [ReportBatchController::class, 'show'])->can('view', 'batch');
    Route::post('report-batches/{batch}/cancel', [ReportBatchController::class, 'cancel'])->can('cancel', 'batch');
    Route::get('reports/{report}', [ReportController::class, 'show'])->can('view', 'report');
    Route::post('logout', [AuthController::class, 'logout']);
});
