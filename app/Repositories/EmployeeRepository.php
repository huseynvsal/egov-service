<?php

namespace App\Repositories;

use App\Contracts\EmployeeRepositoryInterface;
use App\Models\Employee;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    public function findByPin(string $pin): ?Employee
    {
        return Employee::where('pin', $pin)->first();
    }

    public function upsertByPin(string $pin, array $data): Employee
    {
        return Employee::updateOrCreate(['pin' => $pin], $data);
    }
}
