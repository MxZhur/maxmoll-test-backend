<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockHistoryController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// NOTE: API-методы добавлены без авторизации в целях тестирования.
// В продакшене её, возможно, стоило бы добавить.

Route::get('/warehouses', [WarehouseController::class, 'index']);
Route::get('/product-stocks', [ProductController::class, 'stocks']);

Route::prefix('/orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::put('/{id}', [OrderController::class, 'update']);
    Route::put('/{id}/complete', [OrderController::class, 'complete']);
    Route::put('/{id}/cancel', [OrderController::class, 'cancel']);
    Route::put('/{id}/restore', [OrderController::class, 'restore']);
});

Route::get('/stock-history', [StockHistoryController::class, 'index']);
