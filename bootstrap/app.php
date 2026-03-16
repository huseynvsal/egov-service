<?php

use App\Console\Commands\NotifyBalance;
use App\Http\Middleware\ValidateApiKey;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        $exceptions->renderable(function (Throwable $e) {
            if (Config::get('app.debug')) {
                return null;
            }

            $status = match (true) {
                $e instanceof ValidationException => 422,
                $e instanceof HttpException => $e->getStatusCode(),
                default => 500,
            };

            $message = match (true) {
                $e instanceof ValidationException => $e->validator->errors()->first(),
                $status === 500 => 'Internal Server Error',
                default => $e->getMessage(),
            };

            return response()->json(['code' => $status, 'message' => $message], $status);
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command(NotifyBalance::class)->hourly();
    })
    ->create();
