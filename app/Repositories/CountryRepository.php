<?php

namespace App\Repositories;

use App\Contracts\CountryRepositoryInterface;
use App\Models\Country;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CountryRepository implements CountryRepositoryInterface
{
    public function getNumCodeByName(string $name): ?string
    {
        $cacheKey = 'country_num_code_' . md5(strtolower($name));

        return Cache::rememberForever($cacheKey, function () use ($name) {
            $country = Country::where('country_name', 'LIKE', '%' . $name . '%')->first();

            return $country?->num_code ? (string) $country->num_code : null;
        });
    }

    public function yearlyReport(): Collection
    {
        return collect();
    }
}
