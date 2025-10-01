<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// User management routes (users with user permissions)
Route::middleware(['auth', 'permission:users.view'])->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
});

Route::middleware(['auth', 'permission:users.create'])->group(function () {
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
});

Route::middleware(['auth', 'permission:users.view'])->group(function () {
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
});

Route::middleware(['auth', 'permission:users.edit'])->group(function () {
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
});

Route::middleware(['auth', 'permission:users.delete'])->group(function () {
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});

// Permission management routes (only admin can access)
Route::middleware(['auth', 'permission:permissions.view'])->group(function () {
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::get('permissions/{role}', [PermissionController::class, 'show'])->name('permissions.show');
    Route::get('permissions/{role}/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
    Route::put('permissions/{role}', [PermissionController::class, 'update'])->name('permissions.update');
});

// Orders routes (users with orders permissions)
Route::middleware(['auth', 'permission:orders.view'])->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('orders/{order}/invoice/download', [OrderController::class, 'downloadInvoice'])->name('orders.invoice.download');
    Route::post('orders/{order}/refresh-tracking', [OrderController::class, 'refreshTracking'])->name('orders.refresh-tracking');
});

// Order status update (users with orders.status.update permission)
Route::middleware(['auth', 'permission:orders.status.update'])->group(function () {
    Route::patch('orders/{order}', [OrderController::class, 'update'])->name('orders.update');
});

// Order import and sync (users with orders.import permission)
Route::middleware(['auth', 'permission:orders.import'])->group(function () {
    Route::post('orders/fetch', [OrderController::class, 'fetchManual'])->name('orders.fetch');
    Route::post('orders/import-and-sync', [OrderController::class, 'importAndSync'])->name('orders.import-and-sync');
    Route::post('orders/sync-bling', [OrderController::class, 'syncBlingData'])->name('orders.sync-bling');
    Route::post('orders/{order}/sync-bling', [OrderController::class, 'syncBlingDataForOrder'])->name('orders.sync-bling-order');
});

// Profile routes (All authenticated users)
Route::middleware(['auth'])->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
