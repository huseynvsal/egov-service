<?php

namespace App\Actions\Residence;

use App\Concerns\FormatsApiDate;
use App\Models\Residence;
use App\Services\CountryCodeService;

class FormatResidenceData
{
    use FormatsApiDate;
    private const GENDER_MAP = [
        'Kişi' => '1',
        'Qadın' => '2',
    ];

    private const DOCUMENT_TYPE_MAP = [
        'Daimi yaşama icazə vəsiqəsi' => 'DYI',
        'Müvəqqəti yaşama icazə vəsiqəsi' => 'MYI',
    ];

    private const AZERBAIJAN_NUM_CODE = '31';
    private const ISSUE_ORGANIZATION = 'Dövlət Miqrasiya Xidməti';

    public function __construct(private readonly CountryCodeService $countryCodeService)
    {
    }

    public function handle(Residence $residence): array
    {
        $address = $this->parseResidenceAddress($residence->RegistrationAddress);
        $name = $residence->Name ?? '';
        $surname = $residence->Surname ?? '';

        return [
            'base64image' => $residence->Image,
            'clientName' => trim("{$name} {$surname}"),
            'name' => $name,
            'lastname' => $surname,
            'patronymic' => null,
            'clientBirthDate' => $this->formatDate($residence->BirthDate ?? ''),
            'clientBirthCountry' => $this->countryCodeService->getNumericCode($residence->Citizenship ?? ''),
            'clientBirthCity' => $residence->BirthAddress ?? '',
            'clientBirthDistrict' => '',
            'citizenship' => $residence->Citizenship ?? '',
            'clientCity' => $address['City'] ?? '',
            'clientDistrict' => $address['District'] ?? '',
            'clientStreet' => $address['Street'] ?? '',
            'clientBuilding' => $address['Building'] ?? '',
            'clientApt' => $address['Apt'] ?? '',
            'clientPassportIssueAt' => $this->formatDate($residence->GivenDate ?? ''),
            'clientPassportIssueOrganization' => self::ISSUE_ORGANIZATION,
            'clientPassportExpiresAt' => $this->formatDate($residence->ExpireDate ?? ''),
            'clientPassportFin' => $residence->PIN,
            'clientPassportSerialNumber' => $residence->DocumentNumber ?? '',
            'clientGender' => self::GENDER_MAP[$residence->Gender] ?? '1',
            'clientCountry' => self::AZERBAIJAN_NUM_CODE,
            'clientMarital' => '1',
            'clientNationality' => $this->countryCodeService->getNumericCode($residence->Citizenship ?? ''),
            'documentType' => self::DOCUMENT_TYPE_MAP[$residence->DocumentType] ?? $residence->DocumentType,
        ];
    }

    private function parseResidenceAddress(mixed $address): array
    {
        if (is_array($address)) {
            return array_filter($address);
        }

        if (is_string($address)) {
            $decoded = json_decode($address, true);
            if (is_array($decoded)) {
                return array_filter($decoded);
            }
        }

        return [];
    }
}
