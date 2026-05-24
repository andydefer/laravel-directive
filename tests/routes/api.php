<?php

use Illuminate\Support\Facades\Route;

Route::get('/test-api', function () {
    return response()->json([
        'message' => 'API route test OK',
        'status' => 'success',
    ]);
});
