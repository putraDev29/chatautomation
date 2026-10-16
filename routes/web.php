<?php

use App\Http\Controllers\Admin\CarCatalogController;
use App\Http\Controllers\Admin\ConversationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\WhatsAppSettingsController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Guest Authentication Routes
Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Customer WhatsApp Smartphone Simulator
Route::get('/simulator', [\App\Http\Controllers\CustomerSimulatorController::class, 'index'])->name('simulator');
Route::post('/simulator/send', [\App\Http\Controllers\CustomerSimulatorController::class, 'sendMessage'])->name('simulator.send');

// Protected Admin Dashboard Routes
Route::prefix('admin')->middleware('auth')->name('admin.')->group(function () {
    // 1. Dashboard Overview
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 2. WhatsApp Inbox & Conversations
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('/conversations/{conversation}/takeover', [ConversationController::class, 'takeover'])->name('conversations.takeover');
    Route::post('/conversations/{conversation}/resume', [ConversationController::class, 'resumeBot'])->name('conversations.resume');
    Route::post('/conversations/{conversation}/reply', [ConversationController::class, 'sendReply'])->name('conversations.reply');
    Route::post('/conversations/{conversation}/simulate', [ConversationController::class, 'simulateCustomerMessage'])->name('conversations.simulate');
    Route::put('/conversations/{conversation}/details', [ConversationController::class, 'updateDetails'])->name('conversations.details');
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');

    // 3. Cars Showroom Inventory
    Route::get('/cars', [CarCatalogController::class, 'index'])->name('cars.index');
    Route::put('/cars/{car}/status', [CarCatalogController::class, 'updateStatus'])->name('cars.updateStatus');

    // 4. Leads & Pipeline
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');

    // 5. WhatsApp & AI Integration Settings
    Route::get('/settings', [WhatsAppSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [WhatsAppSettingsController::class, 'update'])->name('settings.update');
});
