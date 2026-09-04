<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function(){
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::apiResource('habits', HabitController::class);
    
    Route::post('/habits/{habit}/checkin', [CheckInController::class, 'store']);
    Route::delete('/habits/{habit}/checkin', [CheckInController::class, 'destroy']);
    Route::get('/habits/{habit}/logs', [CheckInController::class, 'logs']);

    Route::get('/dashboard/summary', [ReportController::class, 'summary']);
    Route::get('/dashboard/week', [ReportController::class, 'week']);
    Route::get('/leaderboard', [ReportController::class, 'leaderboard']);
    Route::get('/habits/{habit}/weekly-stats', [ReportController::class, 'weeklyStats']);
});