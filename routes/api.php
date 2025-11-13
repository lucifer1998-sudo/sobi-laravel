<?php

use App\Http\Controllers\Api\AmentiesController;
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
    Route::get('/hosts', [UsersController::class, 'getHostsList']);
    Route::resource('users',UsersController::class);
    // Add POST route for updates to handle file uploads
    Route::post('users/{id}', [UsersController::class, 'update'])->where('id', '[0-9]+');
    Route::resource('roles',RolesController::class);
    Route::resource('amenities',AmentiesController::class);

    Route::prefix('/a/listings/')->group(function (){
        Route::get('table', [PropertiesController::class, 'getListingsTable']);
        Route::get('{id}', [PropertiesController::class, 'show']);
        Route::post('{id}',[PropertiesController::class,'update']);
        Route::post('{id}/photos/update',[PropertiesController::class,'updatePhotos']);
        Route::post('{id}/photos/upload',[PropertiesController::class,'uploadPhotos']);
        Route::post('{id}/photo/delete',[PropertiesController::class,'deletePhoto']);
    });



});
