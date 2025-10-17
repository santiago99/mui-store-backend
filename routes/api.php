<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::apiResource('posts', PostController::class);
    // Route::post('/logout', [AuthController::class, 'logout']);
});

// Product and Category routes (public for testing purposes)
Route::prefix('v1')->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::apiResource('categories', CategoryController::class);

    // Additional category routes
    Route::get('categories-tree', [CategoryController::class, 'tree']);
    Route::post('categories/{category}/move', [CategoryController::class, 'move']);
    Route::get('categories/{category}/products', [CategoryController::class, 'products']);
});
