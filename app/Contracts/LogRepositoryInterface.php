<?php

namespace App\Contracts;

interface LogRepositoryInterface
{
    public function add(string $pin, int $type): void;

    public function yearlyReport(): \Illuminate\Support\Collection;
}
