<?php

namespace App\Repositories;

use App\Contracts\ResidenceRepositoryInterface;
use App\Models\Residence;

class ResidenceRepository implements ResidenceRepositoryInterface
{
    public function findByPin(string $pin): ?Residence
    {
        return Residence::where('PIN', $pin)->first();
    }

    public function upsertByPin(string $pin, array $data): Residence
    {
        return Residence::updateOrCreate(['PIN' => $pin], $data);
    }
}
