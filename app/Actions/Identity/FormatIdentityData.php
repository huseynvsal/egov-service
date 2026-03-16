<?php

namespace App\Actions\Identity;

use App\Concerns\FormatsApiDate;
use App\Models\Identity;
use App\Services\CountryCodeService;

class FormatIdentityData
{
    use FormatsApiDate;
    private const AZ_LOWER_MAP = [
        'Ğ' => 'ğ', 'Ü' => 'ü', 'Ş' => 'ş', 'İ' => 'i',
        'Ö' => 'ö', 'Ç' => 'ç', 'I' => 'ı',
    ];

    private const GENDER_MAP = [
        'Kişi' => '1',
        'Qadın' => '2',
    ];

    private const MARITAL_MAP = [
        'Evli' => '2',
        'Boşanmış' => '3',
        'Dul' => '4',
    ];

    private const AZERBAIJAN_NUM_CODE = '31';

    public function __construct(private readonly CountryCodeService $countryCodeService)
    {
    }

    public function handle(Identity $identity): array
    {
        $name = $this->toTitleCase($identity->Name ?? '');
        $surname = $this->toTitleCase($identity->Surname ?? '');
        $patronymic = $this->cleanPatronymic($identity->Patronymic ?? '');
        $address = $this->parsePersonalAddress($identity->RegistrationAddress ?? '');

        return [
            'base64image' => $identity->Image,
            'clientName' => trim("{$name} {$identity->Patronymic} {$surname}"),
            'name' => $name,
            'lastname' => $surname,
            'patronymic' => $patronymic,
            'clientBirthDate' => $this->formatDate($identity->BirthDate ?? ''),
            'clientBirthCountry' => $this->countryCodeService->getNumericCode($identity->Citizenship ?? ''),
            'clientBirthCity' => $this->extractBirthCity($identity->BirthAddress ?? ''),
            'clientBirthDistrict' => '',
            'citizenship' => $this->cleanCitizenship($identity->Citizenship ?? ''),
            'clientCity' => $address['city'],
            'clientDistrict' => $address['district'],
            'clientStreet' => $address['street'],
            'clientBuilding' => $address['building'],
            'clientApt' => $address['apt'],
            'clientPassportIssueAt' => $this->formatDate($identity->GivenDate ?? ''),
            'clientPassportIssueOrganization' => $identity->GivenOrganization,
            'clientPassportExpiresAt' => $this->formatDate($identity->ExpireDate ?? ''),
            'clientPassportFin' => $identity->PIN,
            'clientPassportSerialNumber' => ($identity->DocumentSeria ?? '') . ($identity->DocumentNumber ?? ''),
            'clientGender' => self::GENDER_MAP[$identity->Gender] ?? '1',
            'clientCountry' => self::AZERBAIJAN_NUM_CODE,
            'clientMarital' => self::MARITAL_MAP[$identity->MaritalStatus] ?? '1',
            'clientNationality' => $this->countryCodeService->getNumericCode($identity->Citizenship ?? ''),
        ];
    }

    private function toTitleCase(string $value): string
    {
        $value = strtr($value, self::AZ_LOWER_MAP);
        $value = mb_strtolower($value);

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    private function cleanPatronymic(string $patronymic): string
    {
        $patronymic = strtr($patronymic, self::AZ_LOWER_MAP);
        $patronymic = mb_strtolower($patronymic);
        $patronymic = preg_replace('/ oğlu$| qızı$/', '', $patronymic);

        return trim($patronymic);
    }

    private function parsePersonalAddress(string $address): array
    {
        $city = '';
        $district = '';
        $street = '';
        $building = '';
        $apt = '';

        if (preg_match('/([^,]+)\s+rayonu/u', $address, $m)) {
            $district = trim($m[1]);
            if (preg_match('/^([^,]+),/u', $address, $cm)) {
                $city = trim($cm[1]);
            }
        } elseif (preg_match('/^([^,]+)/u', $address, $cm)) {
            $city = trim($cm[1]);
        }

        if (preg_match('/([^,]+)\s+küç/u', $address, $m)) {
            $street = trim($m[1]);
        } elseif (preg_match('/([^,]+)\s+pr\b/u', $address, $m)) {
            $street = trim($m[1]);
        }

        if (preg_match('/ev\s+([^,]+)/u', $address, $m)) {
            $building = trim($m[1]);
        } elseif (preg_match('/bina\s+([^,]+)/u', $address, $m)) {
            $building = trim($m[1]);
        }

        if (preg_match('/mən\s+(\d+)/u', $address, $m)) {
            $apt = trim($m[1]);
        } elseif (preg_match('/m\.(\d+)/u', $address, $m)) {
            $apt = trim($m[1]);
        }

        return compact('city', 'district', 'street', 'building', 'apt');
    }

    private function extractBirthCity(string $birthAddress): string
    {
        if (str_contains($birthAddress, ',')) {
            return trim(explode(',', $birthAddress)[0]);
        }

        return trim($birthAddress);
    }

    private function cleanCitizenship(string $citizenship): string
    {
        return trim(preg_replace('/\b(republic|of)\b/i', '', $citizenship));
    }
}
