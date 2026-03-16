<?php

namespace App\Http\Controllers;

use App\Actions\Residence\GetResidenceData;
use App\Http\Requests\ResidenceInfoRequest;
use Illuminate\Http\JsonResponse;

class ResidenceInfoController extends Controller
{
    public function __invoke(ResidenceInfoRequest $request, GetResidenceData $action): JsonResponse
    {
        return $this->success($action->handle($request->validated('fin')));
    }
}
