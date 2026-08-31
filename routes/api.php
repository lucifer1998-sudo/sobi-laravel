<?php

use App\Http\Controllers\Api\AmentiesController;
use App\Http\Controllers\Api\LeadsController;
use App\Http\Controllers\Api\LeasesController;
use App\Http\Controllers\Api\PropertiesController;
use App\Http\Controllers\Api\RentalApplicationsController;
use App\Http\Controllers\Api\RolesController;
use App\Http\Controllers\Api\SiteContentController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/properties', [PropertiesController::class, 'index']);
// Must stay above /properties/{id}, otherwise these are read as an id.
Route::get('/properties/locations', [PropertiesController::class, 'getLocations']);
Route::get('/properties/options', [PropertiesController::class, 'getOptions']);
Route::get('/properties/{id}', [PropertiesController::class, 'showPublic']);

// Public forms post enquiries here.
Route::post('/leads', [LeadsController::class, 'store']);

// The rental application wizard is a guest flow, so these three are open.
// The receipt is addressed by the random application_id, never the row id, and
// the upload endpoint is reachable only through a link signed by us.
Route::post('/applications', [RentalApplicationsController::class, 'store']);
Route::get('/applications/{applicationId}', [RentalApplicationsController::class, 'show']);
Route::post('/applications/{applicationId}/documents', [RentalApplicationsController::class, 'storeDocuments'])
    ->middleware('signed')
    ->name('applications.documents.store');

// The site copy the public pages read.
Route::get('/content', [SiteContentController::class, 'index']);

/**
 * Protected API routes - require authentication via Sanctum
 */
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', [UsersController::class, 'getAuthUser']);
    Route::get('/hosts', [UsersController::class, 'getHostsList']);
    Route::resource('users', UsersController::class);
    // Add POST route for updates to handle file uploads
    Route::post('users/{id}', [UsersController::class, 'update'])->where('id', '[0-9]+');
    Route::resource('roles', RolesController::class);

    // Must stay above /leads/{id}, otherwise "sources" is read as an id.
    Route::get('leads/sources', [LeadsController::class, 'getSources']);
    Route::get('leads', [LeadsController::class, 'index']);
    Route::delete('leads/{id}', [LeadsController::class, 'destroy']);

    // The admin side of applications. Reading one application is public above,
    // because the applicant needs their own receipt without an account.
    Route::get('applications', [RentalApplicationsController::class, 'index']);
    Route::delete('applications/{applicationId}', [RentalApplicationsController::class, 'destroy']);
    Route::get('applications/{applicationId}/upload-link', [RentalApplicationsController::class, 'documentUploadLink']);
    Route::get('applications/{applicationId}/signature', [RentalApplicationsController::class, 'downloadSignature']);
    Route::get('applications/{applicationId}/documents/{documentId}', [RentalApplicationsController::class, 'downloadDocument']);

    Route::resource('amenities', AmentiesController::class);

    Route::prefix('/a/listings/')->group(function () {
        Route::get('table', [PropertiesController::class, 'getListingsTable']);
        Route::get('{id}', [PropertiesController::class, 'show']);
        Route::post('{id}', [PropertiesController::class, 'update']);
        Route::post('{id}/listed', [PropertiesController::class, 'updateListedStatus']);
        Route::post('{id}/translations/{locale}', [PropertiesController::class, 'updateTranslation']);
        Route::post('{id}/photos/update', [PropertiesController::class, 'updatePhotos']);
        Route::post('{id}/photos/upload', [PropertiesController::class, 'uploadPhotos']);
        Route::post('{id}/photo/delete', [PropertiesController::class, 'deletePhoto']);
    });

    // The editor reads the raw values for one language, not the merged ones the
    // public endpoint serves, so a save cannot write English into a translation.
    Route::get('a/content/{locale}', [SiteContentController::class, 'adminIndex']);
    Route::post('a/content/{section}', [SiteContentController::class, 'update']);

    Route::prefix('/a/leases')->group(function () {
        Route::get('/', [LeasesController::class, 'index']);
        Route::get('{signatureId}', [LeasesController::class, 'show']);
    });

});
