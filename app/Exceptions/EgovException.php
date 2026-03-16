<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class EgovException extends Exception
{
    public function __construct(string $message, protected int $statusCode = 400)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'code'    => $this->statusCode,
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }
}
