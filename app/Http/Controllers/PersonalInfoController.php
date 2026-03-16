<?php

namespace App\Http\Controllers;

use App\Actions\Identity\GetPersonalData;
use App\Http\Requests\PersonalInfoRequest;
use Illuminate\Http\JsonResponse;

class PersonalInfoController extends Controller
{
    public function __invoke(PersonalInfoRequest $request, GetPersonalData $action): JsonResponse
    {
        return $this->success($action->handle($request->validated('fin'), $request->validated('docNumber')));
    }
}
