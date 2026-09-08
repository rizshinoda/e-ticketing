<?php

use App\Http\Controllers\RestitutionController;
use App\Http\Controllers\TicketCategoryController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('tickets')
        ->name('tickets.')
        ->group(function () {

            Route::get('/', [
                TicketController::class,
                'index',
            ])->name('index');

            Route::get('/create', [
                TicketController::class,
                'create',
            ])->name('create');

            Route::post('/', [
                TicketController::class,
                'store',
            ])->name('store');

            Route::get('/{ticket}', [
                TicketController::class,
                'show',
            ])->name('show');

            Route::post('/{ticket}/updates', [
                TicketController::class,
                'storeUpdate',
            ])->name('updates.store');

            Route::post('/{ticket}/resolve', [
                TicketController::class,
                'resolve',
            ])->name('resolve');

            // =====================================================
            // STOP CLOCK - TICKET LEVEL
            // =====================================================

            Route::post('/{ticket}/stop-clock', [
                TicketController::class,
                'startStopClock',
            ])->name('stop-clock.start');

            Route::post('/{ticket}/stop-clock/resume', [
                TicketController::class,
                'endStopClock',
            ])->name('stop-clock.end');
        });
    Route::prefix('master')
        ->name('master.')
        ->group(function () {

            Route::get(
                '/kendala',
                [TicketCategoryController::class, 'index']
            )->name('kendala.index');

            Route::post(
                '/kendala',
                [TicketCategoryController::class, 'store']
            )->name('kendala.store');

            Route::put(
                '/kendala/{kendala}',
                [TicketCategoryController::class, 'update']
            )->name('kendala.update');

            Route::delete(
                '/kendala/{kendala}',
                [TicketCategoryController::class, 'destroy']
            )->name('kendala.destroy');
        });

    Route::prefix('restitution')
        ->name('restitution.')
        ->group(function () {
            Route::get('/', [
                RestitutionController::class,
                'index',
            ])->name('index');

            Route::post('/calculate', [
                RestitutionController::class,
                'calculate',
            ])->name('calculate');
            Route::get('/sites/{pelanggan}', [
                RestitutionController::class,
                'sites',
            ])->name('sites');
        });
});

require __DIR__ . '/settings.php';
