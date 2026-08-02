<?php

use App\Http\Controllers\PowerAutomateController;
use App\Http\Middleware\VerifyPowerAutomateApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyPowerAutomateApiKey::class)->group(function () {
    Route::post('/power-automate/email-notes', [PowerAutomateController::class, 'storeEmailMessageNote'])
        ->name('api.power-automate.email-notes.store');
});
