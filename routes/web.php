<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Servicios Pro Backend',
    ]);
});

// Expose runtime configuration for the frontend (small JS snippet).
// The Google Maps API key is read from the environment variable
// `GOOGLE_MAPS_API_KEY`. This avoids committing the key into static HTML.
Route::get('/config.js', function () {
    $key = env('GOOGLE_MAPS_API_KEY', '');
    $js = 'window.__APP_CONFIG = { MAPS_KEY: ' . json_encode($key) . ' };';
    return response($js, 200)->header('Content-Type', 'application/javascript');
});
