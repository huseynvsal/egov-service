<?php

namespace App\Repositories;

use App\Contracts\IdentityRepositoryInterface;
use App\Models\Identity;
use Illuminate\Support\Facades\Cache;

class IdentityRepository implements IdentityRepositoryInterface
{
    private const CACHE_TTL_HOURS = 6;

    public function findByPin(string $pin): ?Identity
    {
        return Cache::remember("identity:{$pin}", now()->addHours(self::CACHE_TTL_HOURS), function () use ($pin) {
            return Identity::where('PIN', $pin)->first();
        });
    }

    public function upsertByPin(string $pin, array $data): Identity
    {
        Identity::where('PIN', $pin)->delete();

        $identity = Identity::create(array_merge(['PIN' => $pin], $data));

        Cache::put("identity:{$pin}", $identity, now()->addHours(self::CACHE_TTL_HOURS));

        return $identity;
    }
}
