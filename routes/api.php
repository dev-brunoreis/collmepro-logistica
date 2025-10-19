<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function (Request $request) {
    $userAgent = $request->header('User-Agent');
    
    if ($userAgent !== 'DevHub-HealthChecker/1.0') {
        return response()->json([
            'error' => 'Unauthorized'
        ], 403);
    }
    
    return response()->json([
        'status' => 'pong'
    ]);
});

