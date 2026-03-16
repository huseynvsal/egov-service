<?php

namespace App\Providers;

use App\Contracts\EmployeeRepositoryInterface;
use App\Contracts\IdentityRepositoryInterface;
use App\Contracts\LogRepositoryInterface;
use App\Contracts\ResidenceRepositoryInterface;
use App\Repositories\EmployeeRepository;
use App\Repositories\IdentityRepository;
use App\Repositories\LogRepository;
use App\Repositories\ResidenceRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IdentityRepositoryInterface::class, IdentityRepository::class);
        $this->app->bind(ResidenceRepositoryInterface::class, ResidenceRepository::class);
        $this->app->bind(EmployeeRepositoryInterface::class, EmployeeRepository::class);
$this->app->bind(LogRepositoryInterface::class, LogRepository::class);
    }

    public function boot(): void
    {
    }
}
