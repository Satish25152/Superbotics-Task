<?php

use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\RedemptionController;
use App\Http\Controllers\Api\RedemptionReportController;
use Illuminate\Support\Facades\Route;

Route::post('/redemptions', [RedemptionController::class, 'store']);
Route::get('/users/{id}/balance', [BalanceController::class, 'show'])->whereNumber('id');
Route::get('/reports/redemptions', [RedemptionReportController::class, 'index']);
