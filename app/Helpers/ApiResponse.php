<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use App\Enums\ResponseCode\HttpStatusCode;

class ApiResponse
{
    public static function success(mixed $data = [], string $message = '', HttpStatusCode $status = HttpStatusCode::OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ], $status->value)->withHeaders([
            'Accept'=>'application/json'
        ]);
    }

    public static function error(string $message = '', mixed $errors = [], HttpStatusCode $status = HttpStatusCode::BAD_REQUEST): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors
        ], $status->value)->withHeaders([
            'Accept'=>'application/json'
        ]);
    }
}
