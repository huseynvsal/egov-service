<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Api-Key') !== config('app.api_key')) {
            abort(401, 'Unauthorized');
        }

        return $next($request);
    }
}
