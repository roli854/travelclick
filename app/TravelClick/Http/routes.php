<?php

use App\TravelClick\Http\Controllers\SoapController;
use App\TravelClick\Http\Middleware\SoapAuthMiddleware;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| TravelClick SOAP API Routes
|--------------------------------------------------------------------------
|
| Here are the routes for the TravelClick HTNG 2011B interface. These routes
| handle incoming SOAP requests from TravelClick for bidirectional integration.
|
*/

Route::prefix('api/travelclick')->name('travelclick.')->group(function () {
  // WSDL route - provides the WSDL file for TravelClick
  Route::get('soap/wsdl', function () {
    $response = response()->file(storage_path('soap/wsdl/travelclick.wsdl'));
    $response->headers->set('Content-Type', 'text/xml');
    return $response;
  })->name('wsdl');

  // Main SOAP endpoint to handle all incoming messages
  Route::post('soap', [SoapController::class, 'handle'])
    ->middleware(SoapAuthMiddleware::class)
    ->name('soap');

  // Health check endpoint
  Route::get('health', function () {
    return response()->json([
      'status' => 'ok',
      'version' => config('travelclick.version', '1.0.0'),
      'timestamp' => now()->toIso8601String(),
    ]);
  })->name('health');
});
