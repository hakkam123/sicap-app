<?php

use App\Http\Controllers\Api\ConsumeApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Endpoint sinkronisasi consume dengan perlindungan rate limiter
    Route::post('/consumes/sync', [ConsumeApiController::class, 'sync'])
        ->middleware('throttle:api-consume-sync');

    Route::post('/tokens/create', function (Request $request) {
        $tokenName = $request->input('token_name', 'api-sync-token');
        $abilities = (array) $request->input('abilities', ['consumes:sync']);
        $token = $request->user()->createToken($tokenName, $abilities);

        return response()->json([
            'status' => 'success',
            'token' => $token->plainTextToken,
            'abilities' => $abilities,
        ]);
    })->middleware('throttle:api-token-create');
});