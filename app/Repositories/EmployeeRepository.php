<?php

namespace App\Repositories;

use App\Contracts\EmployeeRepositoryInterface;
use App\Models\Employee;
use Illuminate\Support\Facades\Cache;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    private const CACHE_TTL_HOURS = 6;

    public function findByPin(string $pin): ?Employee
    {
        return Cache::remember("employee:{$pin}", now()->addHours(self::CACHE_TTL_HOURS), function () use ($pin) {
            return Employee::where('pin', $pin)->first();
        });
    }

    public function upsertByPin(string $pin, array $data): Employee
    {
        Employee::where('pin', $pin)->delete();

        $employee = Employee::create(array_merge(['pin' => $pin], $data));

        Cache::put("employee:{$pin}", $employee, now()->addHours(self::CACHE_TTL_HOURS));

        return $employee;
    }
}
