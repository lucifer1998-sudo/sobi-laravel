<?php

use App\Http\Controllers\Api\RolesController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Protected API routes - require authentication via Sanctum
 */
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/user', [UsersController::class, 'getAuthUser']);
    Route::resource('users',UsersController::class);
    Route::resource('roles',RolesController::class);
});
