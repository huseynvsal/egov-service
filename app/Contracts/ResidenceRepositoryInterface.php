<?php

namespace App\Contracts;

use App\Models\Residence;

interface ResidenceRepositoryInterface
{
    public function findByPin(string $pin): ?Residence;
    public function upsertByPin(string $pin, array $data): Residence;
}
