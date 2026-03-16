<?php

use App\Console\Commands\NotifyBalance;
use App\Http\Middleware\ValidateApiKey;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'validate-api-key' => ValidateApiKey::class,
        ]);

        $middleware->api(append: [
            HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (ValidationException $e) {
            return response()->json(['code' => 422, 'message' => $e->validator->errors()->first(), 'data' => null], 422);
        });

        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['code' => 404, 'message' => 'Route not defined', 'data' => null], 404);
            }
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command(NotifyBalance::class)->hourly();
    })
    ->create();
