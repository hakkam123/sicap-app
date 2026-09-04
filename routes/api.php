<?php

use App\Http\Controllers\Api\ConsumeApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/consumes/sync', [ConsumeApiController::class, 'sync']);

    Route::post('/tokens/create', function (Request $request) {
        $tokenName = $request->input('token_name', 'api-token');
        $token = $request->user()->createToken($tokenName);

        return response()->json([
            'token' => $token->plainTextToken,
        ]);
    });
});

