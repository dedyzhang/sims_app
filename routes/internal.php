<?php

use App\Http\Controllers\Internal\DemoProvisionController;
use Illuminate\Support\Facades\Route;

Route::post('/provisions', [DemoProvisionController::class, 'store'])->name('internal.demo.provisions');
Route::post('/accesses/{demoAccess}/revoke', [DemoProvisionController::class, 'revoke'])->name('internal.demo.revoke');
Route::post('/accesses/{demoAccess}/resend-onboarding', [DemoProvisionController::class, 'resendOnboarding'])->name('internal.demo.resend');
