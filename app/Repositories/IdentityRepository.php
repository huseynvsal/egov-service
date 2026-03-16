<?php

namespace App\Repositories;

use App\Contracts\IdentityRepositoryInterface;
use App\Models\Identity;

class IdentityRepository implements IdentityRepositoryInterface
{
    public function findByPin(string $pin): ?Identity
    {
        return Identity::where('PIN', $pin)->first();
    }

    public function upsertByPin(string $pin, array $data): Identity
    {
        return Identity::updateOrCreate(['PIN' => $pin], $data);
    }
}
