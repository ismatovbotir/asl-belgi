<?php

use App\Http\Controllers\AslBelgisi\AuthController;
use App\Http\Controllers\AslBelgisi\LabelController;
use App\Http\Controllers\AslBelgisi\OrderController;
use App\Http\Controllers\AslBelgisi\ProductController;
use Illuminate\Support\Facades\Route;

Route::name('asl.')->group(function () {

    // Settings & Auth
    Route::get('/settings',              [AuthController::class, 'settings'])->name('settings');
    Route::post('/settings/credentials', [AuthController::class, 'saveCredentials'])->name('settings.save');
    Route::post('/auth/test',    [AuthController::class, 'testCredentials'])->name('auth.test');
    Route::post('/auth/check',   [AuthController::class, 'checkKey'])->name('auth.check');
    Route::post('/auth/refresh', [AuthController::class, 'refreshKey'])->name('auth.refresh');

    // Product Registry
    Route::get('/products',         [ProductController::class, 'index'])->name('products.index');
    Route::post('/products/sync',   [ProductController::class, 'sync'])->name('products.sync');

    // KM Orders
    Route::get('/orders',                           [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/import',                   [OrderController::class, 'import'])->name('orders.import');
    Route::get('/orders/{order}',                   [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/refresh',          [OrderController::class, 'refreshStatus'])->name('orders.refresh');
    Route::post('/orders/{order}/poll',             [OrderController::class, 'pollStatus'])->name('orders.poll');
    Route::post('/orders/{order}/buffers/{item}/download', [OrderController::class, 'downloadBuffer'])->name('orders.download');

    // Label Design
    Route::get('/labels',                           [LabelController::class, 'index'])->name('labels.index');
    Route::get('/labels/{order}',                   [LabelController::class, 'designer'])->name('labels.designer');
    Route::get('/labels/{order}/print',             [LabelController::class, 'print'])->name('labels.print');
    Route::post('/labels/{order}/mark-printed',     [LabelController::class, 'markPrinted'])->name('labels.markPrinted');
    Route::post('/labels/{order}/generate-pdf',     [LabelController::class, 'generatePdf'])->name('labels.generatePdf');
    Route::get('/labels/{order}/pdf/{file}',        [LabelController::class, 'downloadPdf'])->name('labels.downloadPdf');
});
