<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductClassController;
use App\Http\Controllers\Admin\ProductFieldController;
use App\Http\Controllers\Api\CategoryController as ApiCategoryController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\PostController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::put('/user', [UserController::class, 'update']);
    Route::put('/user/password', [UserController::class, 'updatePassword']);
    Route::apiResource('posts', PostController::class);

    // Cart routes
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::post('/cart/merge', [CartController::class, 'merge']);
    Route::put('/cart/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);

    // Route::post('/logout', [AuthController::class, 'logout']);
});

// Product and Category routes (public for testing purposes)
Route::prefix('v1')->group(function () {
    // Public API routes (read-only)
    Route::get('products', [ApiProductController::class, 'index']);
    Route::get('products/{product}', [ApiProductController::class, 'show']);
    Route::get('categories', [ApiCategoryController::class, 'index']);
    Route::get('categories/{category}', [ApiCategoryController::class, 'show']);
    Route::get('categories/{category}/products', [ApiCategoryController::class, 'products']);
});

// Admin routes (mutating operations) - require admin middleware and /admin prefix
Route::middleware(['auth:sanctum', 'admin'])->prefix('v1/admin')->group(function () {
    Route::get('ping', fn() => response()->json(['status' => 'ok']));
    
    Route::post('products', [AdminProductController::class, 'store']);
    Route::put('products/{product}', [AdminProductController::class, 'update']);
    Route::delete('products/{product}', [AdminProductController::class, 'destroy']);
    
    Route::post('categories', [AdminCategoryController::class, 'store']);
    Route::put('categories/{category}', [AdminCategoryController::class, 'update']);
    Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy']);
    Route::post('categories/{category}/move', [AdminCategoryController::class, 'move']);
    
    Route::apiResource('product-classes', ProductClassController::class);
    Route::apiResource('product-fields', ProductFieldController::class);    
});