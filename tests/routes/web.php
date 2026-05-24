<?php

use Illuminate\Support\Facades\Route;

Route::get('/test-web', function () {
    return response()->json([
        'message' => 'Web route test OK',
        'status' => 'success',
    ]);
});
