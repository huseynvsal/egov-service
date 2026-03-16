<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function success(mixed $data, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message, 'data' => $data], $code)
            ->header('Content-Type', 'application/json;charset=UTF-8');
    }
}
