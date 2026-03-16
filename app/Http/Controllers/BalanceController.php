<?php

namespace App\Http\Controllers;

use App\Services\AsanFinanceService;
use Illuminate\Http\JsonResponse;

class BalanceController extends Controller
{
    public function __invoke(AsanFinanceService $service): JsonResponse
    {
        return $this->success($service->getBalance()['Response']);
    }
}
