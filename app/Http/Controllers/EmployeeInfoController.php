<?php

namespace App\Http\Controllers;

use App\Actions\Employee\GetEmployeeData;
use App\Http\Requests\EmployeeInfoRequest;
use Illuminate\Http\JsonResponse;

class EmployeeInfoController extends Controller
{
    public function __invoke(EmployeeInfoRequest $request, GetEmployeeData $action): JsonResponse
    {
        return $this->success($action->handle($request->validated('fin')));
    }
}
