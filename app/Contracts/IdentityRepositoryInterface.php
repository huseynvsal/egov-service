<?php

namespace App\Contracts;

use App\Models\Identity;

interface IdentityRepositoryInterface
{
    public function findByPin(string $pin): ?Identity;
    public function upsertByPin(string $pin, array $data): Identity;
}
