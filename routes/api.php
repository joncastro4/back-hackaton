<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PurchaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix("v1")->group(function() {
    Route::post("register", [AuthController::class, 'register']);
    Route::post("login", [AuthController::class, 'login']);
    Route::get("nessie-id", [AuthController::class, 'nessieId']);
    Route::post("create-account", [AccountController::class, 'createAccount']);
    Route::get("purchases", [PurchaseController::class, 'getPurchasesLocations']);
});