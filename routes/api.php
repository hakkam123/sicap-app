<?php

use App\Http\Controllers\Api\ConsumeApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Endpoint sinkronisasi consume dengan perlindungan rate limiter
    Route::post('/consumes/sync', [ConsumeApiController::class, 'sync'])
        ->middleware('throttle:api-consume-sync');

    // Endpoint generate token API dengan perlindungan rate limiter
    Route::post('/tokens/create', function (Request $request) {
        $tokenName = $request->input('token_name', 'api-token');
        $token = $request->user()->createToken($tokenName);

        return response()->json([
            'token' => $token->plainTextToken,
        ]);
    })->middleware('throttle:api-token-create');
});