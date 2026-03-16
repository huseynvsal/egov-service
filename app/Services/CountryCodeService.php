<?php

namespace App\Services;

use EchoLabs\Prism\Enums\Provider;
use EchoLabs\Prism\Prism;
use Illuminate\Support\Facades\Cache;

class CountryCodeService
{
    public function getNumericCode(string $countryName): string
    {
        $cleaned = $this->cleanCountryName($countryName);
        $cacheKey = 'country_code_' . md5(strtolower($cleaned));

        return Cache::rememberForever($cacheKey, function () use ($cleaned) {
            $response = Prism::text()
                ->using(Provider::Anthropic, 'claude-haiku-4-5-20251001')
                ->withPrompt(
                    "Return ONLY the ISO 3166-1 numeric country code (as a plain number, no text) for: {$cleaned}. " .
                    "If unknown, return 0."
                )
                ->generate();

            $code = trim($response->text);

            return preg_match('/^\d+$/', $code) ? $code : '0';
        });
    }

    private function cleanCountryName(string $name): string
    {
        return trim(preg_replace('/\b(republic|of)\b/i', '', $name));
    }
}
