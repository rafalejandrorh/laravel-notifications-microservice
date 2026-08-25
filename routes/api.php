<?php

use App\Http\Controllers\EmailController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TemplateController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::middleware(AuthenticateApiKey::class)->group(function (): void {
    Route::post('/emails', [EmailController::class, 'store']);
    Route::post('/emails/{eventId}/retry', [EmailController::class, 'retry']);
    Route::get('/notifications/{eventId}', [NotificationController::class, 'show']);
    Route::get('/templates', [TemplateController::class, 'index']);
});
