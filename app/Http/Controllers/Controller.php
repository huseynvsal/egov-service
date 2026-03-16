<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function success(mixed $data, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json(array_filter(['code' => $code, 'message' => $message, 'data' => $data], fn ($v) => ! is_null($v)), $code)
            ->header('Content-Type', 'application/json;charset=UTF-8');
    }
}
