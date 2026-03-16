<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Ai;

class CountryCodeService
{
    public function getNumericCode(string $countryName): string
    {
        $cleaned = trim(preg_replace('/\b(republic|of)\b/i', '', $countryName));
        $cacheKey = 'country_code_' . md5(strtolower($cleaned));

        return Cache::rememberForever($cacheKey, function () use ($cleaned) {
            $response = Ai::agent()->prompt(
                "Return ONLY the ISO 3166-1 numeric country code (as a plain number, no text) for: {$cleaned}. " .
                'If unknown, return 0.',
                provider: 'anthropic',
                model: 'claude-haiku-4-5-20251001',
            );

            $code = trim($response->text);

            return preg_match('/^\d+$/', $code) ? $code : '0';
        });
    }
}
