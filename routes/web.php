<?php

use App\Http\Controllers\Admin\GoogleDriveController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
    Route::get('auth/google', [AuthController::class, 'redirectToGoogle'])->name('login.google');
    Route::get('auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('login.google.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::post('dashboard/refresh', [DashboardController::class, 'refresh'])->name('dashboard.refresh');
    Route::post('dashboard/server-health/{server}', [DashboardController::class, 'refreshServer'])
        ->name('dashboard.server-health');
    Route::get('admin/google-drive', [GoogleDriveController::class, 'index'])->name('admin.google-drive.index');
    Route::post('admin/google-drive/connect', [GoogleDriveController::class, 'start'])->name('admin.google-drive.start');
    Route::get('admin/google-drive/callback', [GoogleDriveController::class, 'callback'])->name('admin.google-drive.callback');
    Route::post('admin/google-drive/sync', [GoogleDriveController::class, 'sync'])->name('admin.google-drive.sync');
    Route::post('admin/google-drive/backup', [GoogleDriveController::class, 'backup'])->name('admin.google-drive.backup');
    Route::post('admin/google-drive/restore', [GoogleDriveController::class, 'restore'])->name('admin.google-drive.restore');

    Route::resource('servers', ServerController::class);
    Route::post('servers/{server}/vault', [ServerController::class, 'storeVaultEntry'])->name('servers.vault.store');
    Route::resource('servers.services', ServiceController::class)
        ->shallow()
        ->only(['create', 'store', 'edit', 'update', 'destroy']);

    Route::resource('groups', GroupController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
});
