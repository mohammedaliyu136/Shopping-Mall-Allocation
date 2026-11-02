<?php

use App\Http\Controllers\ActionController;
use App\Http\Controllers\Admin\WardSetupController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\EncounterController;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\NursingController;
use App\Http\Controllers\WardController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', fn () => session('ward_id')
        ? redirect()->route('wards.show', session('ward_id'))
        : redirect()->route(auth()->user()->isAdmin() ? 'admin.wards.index' : 'wards.index'));

    // Ward level
    Route::get('/wards', [WardController::class, 'index'])->name('wards.index');
    Route::post('/wards/{ward}/select', [WardController::class, 'select'])->name('wards.select');
    Route::get('/wards/{ward}', [WardController::class, 'show'])->name('wards.show');
    Route::get('/wards/{ward}/patients', [WardController::class, 'patients'])->name('wards.patients');
    Route::get('/wards/{ward}/config.json', [WardController::class, 'config'])->name('wards.config');
    Route::post('/wards/{ward}/arrivals', [WardController::class, 'registerArrival'])->middleware('role:doctor,nurse')->name('wards.arrivals');
    Route::post('/wards/{ward}/encounters/{encounter}/nurse', [WardController::class, 'assignNurse'])->middleware('role:doctor,nurse')->name('wards.assign-nurse');

    Route::get('/wards/{ward}/handover', [HandoverController::class, 'index'])->name('handover.index');
    Route::middleware('role:doctor,nurse')->group(function () {
        Route::post('/wards/{ward}/handover', [HandoverController::class, 'storeNote'])->name('handover.store');
        Route::post('/wards/{ward}/tasks', [HandoverController::class, 'storeTask'])->name('tasks.store');
        Route::post('/wards/{ward}/tasks/{task}/toggle', [HandoverController::class, 'completeTask'])->name('tasks.toggle');
    });

    // Patient level
    Route::get('/encounters/{encounter}', [EncounterController::class, 'show'])->name('encounters.show');
    Route::get('/encounters/{encounter}/tabs/{key}', [EncounterController::class, 'tab'])->name('encounters.tab');

    Route::middleware('role:doctor,nurse')->group(function () {
        Route::get('/encounters/{encounter}/actions/{key}', [ActionController::class, 'form'])->name('actions.form');
        Route::post('/encounters/{encounter}/actions/{key}', [ActionController::class, 'store'])->name('actions.store');

        Route::post('/encounters/{encounter}/header/{field}', [EncounterController::class, 'updateHeader'])->name('encounters.header');
        Route::post('/entries/{entry}/cosign', [EncounterController::class, 'cosign'])->name('entries.cosign');
        Route::post('/entries/{entry}/acknowledge', [EncounterController::class, 'acknowledge'])->name('entries.acknowledge');
        Route::post('/entries/{entry}/result', [EncounterController::class, 'result'])->name('entries.result');
        Route::post('/transfers/{transfer}/cancel', [EncounterController::class, 'cancelTransfer'])->name('transfers.cancel');

        Route::post('/encounters/{encounter}/risk/{kind}', [NursingController::class, 'riskAssessment'])->name('encounters.risk');
        Route::post('/encounters/{encounter}/discharge-checklist', [NursingController::class, 'dischargeChecklist'])->name('encounters.checklist');
    });

    // Phase 5: admin setup, admin roles only
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/wards', [WardSetupController::class, 'index'])->name('wards.index');
        Route::get('/wards/create', [WardSetupController::class, 'create'])->name('wards.create');
        Route::post('/wards', [WardSetupController::class, 'store'])->name('wards.store');
        Route::get('/wards/{ward}/edit', [WardSetupController::class, 'edit'])->name('wards.edit');
        Route::put('/wards/{ward}', [WardSetupController::class, 'update'])->name('wards.update');
        Route::get('/wards/{ward}/history', [WardSetupController::class, 'history'])->name('wards.history');
    });
});
