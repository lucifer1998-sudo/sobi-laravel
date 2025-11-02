<?php

use App\Http\Controllers\Api\PropertiesController;
use App\Http\Controllers\Api\RolesController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;


Route::get('/properties', [PropertiesController::class, 'index']);
Route::get('/properties/{id}', [PropertiesController::class, 'show']);

/**
 * Protected API routes - require authentication via Sanctum
 */
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/user', [UsersController::class, 'getAuthUser']);
    Route::resource('users',UsersController::class);
    Route::resource('roles',RolesController::class);


});
