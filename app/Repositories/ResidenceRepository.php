<?php

namespace App\Repositories;

use App\Contracts\ResidenceRepositoryInterface;
use App\Models\Residence;
use Illuminate\Support\Facades\Cache;

class ResidenceRepository implements ResidenceRepositoryInterface
{
    private const int CACHE_TTL_HOURS = 6;

    public function findByPin(string $pin): ?Residence
    {
        return Cache::remember("residence:{$pin}", now()->addHours(self::CACHE_TTL_HOURS), function () use ($pin) {
            return Residence::where('PIN', $pin)->first();
        });
    }

    public function upsertByPin(string $pin, array $data): Residence
    {
        Residence::where('PIN', $pin)->delete();

        $residence = Residence::create(array_merge(['PIN' => $pin], $data));

        Cache::put("residence:{$pin}", $residence, now()->addHours(self::CACHE_TTL_HOURS));

        return $residence;
    }
}
