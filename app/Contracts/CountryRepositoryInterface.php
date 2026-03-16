<?php

namespace App\Contracts;

interface CountryRepositoryInterface
{
    public function getNumCodeByName(string $name): ?string;

    public function yearlyReport(): \Illuminate\Support\Collection;
}
