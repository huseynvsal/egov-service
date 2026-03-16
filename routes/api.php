<?php

use App\Http\Controllers\BalanceController;
use App\Http\Controllers\EmployeeInfoController;
use App\Http\Controllers\PersonalInfoController;
use App\Http\Controllers\ResidenceInfoController;
use Illuminate\Support\Facades\Route;

Route::middleware('validate-api-key')->group(function () {
    Route::post('/personal-info', PersonalInfoController::class);
    Route::post('/residence-info', ResidenceInfoController::class);
    Route::post('/employee-info', EmployeeInfoController::class);
    Route::get('/balance', BalanceController::class);
});
