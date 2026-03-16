<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

use function Laravel\Ai\agent;

class CountryCodeService
{
    public function getNumericCode(string $countryName): string
    {
        $cacheKey = 'country_code_' . md5(strtolower($countryName));

        return Cache::rememberForever($cacheKey, function () use ($countryName) {
            $response = agent('You are a country code lookup assistant. Return only numbers, no extra text.')
                ->prompt(
                    "Return ONLY the ISO 3166-1 numeric country code (as a plain number, no text) for: {$countryName}. " .
                    'If unknown, return 0.',
                    provider: 'anthropic',
                    model: 'claude-haiku-4-5-20251001',
                );

            $code = trim($response->text);

            return preg_match('/^\d+$/', $code) ? $code : '0';
        });
    }
}
