<?php

namespace App\Contracts;

use App\Models\Employee;

interface EmployeeRepositoryInterface
{
    public function findByPin(string $pin): ?Employee;

    public function upsertByPin(string $pin, array $data): Employee;
}
