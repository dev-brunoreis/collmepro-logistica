<?php

use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\StockApiController;
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

Route::get('/metrics', [MetricsController::class, 'index']);

// Stock API routes (authenticated with web session)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/stock/by-ids', [StockApiController::class, 'getStockByIds']);
    Route::post('/stock/fetch-stocks', [StockApiController::class, 'fetchStocks']);
});