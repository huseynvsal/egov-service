<?php

namespace App\Actions\Identity;

use App\Concerns\ChecksCacheFreshness;
use App\Contracts\IdentityRepositoryInterface;
use App\Contracts\LogRepositoryInterface;
use App\Models\Identity;
use App\Models\Log;
use App\Services\AsanFinanceService;
use Carbon\Carbon;

class GetPersonalData
{
    use ChecksCacheFreshness;

    public function __construct(
        private readonly AsanFinanceService $asanFinance,
        private readonly IdentityRepositoryInterface $identityRepo,
        private readonly LogRepositoryInterface $logRepo,
        private readonly FormatIdentityData $formatter,
    ) {
    }

    public function handle(string $fin, ?string $docNumber = null): array
    {
        if (config('app.debug') && config('app.env') === 'development') {
            return $this->mockData($fin);
        }

        $identity = $this->resolveIdentity($fin, $docNumber);

        $this->logRepo->add($fin, Log::TYPE_PERSONAL);

        return [
            'raw' => $identity->toArray(),
            'formatData' => $this->formatter->handle($identity),
        ];
    }

    private function resolveIdentity(string $fin, ?string $docNumber): Identity
    {
        $cached = $this->identityRepo->findByPin($fin);

        if ($cached && $this->isFresh($cached)) {
            return $cached;
        }

        return $this->fetchAndStore($fin, $docNumber);
    }

    private function fetchAndStore(string $fin, ?string $docNumber): Identity
    {
        $data = $docNumber
            ? $this->asanFinance->getPersonalInfoByFinAndDoc($fin, $docNumber)
            : $this->asanFinance->getPersonalInfoByFin($fin);

        $response = $data['Response'];

        if (empty($response['ExpireDate'])) {
            $response['ExpireDate'] = Carbon::createFromFormat('d.m.Y', $response['BirthDate'])
                ->addYears(100)
                ->format('d.m.Y');
        }

        return $this->identityRepo->upsertByPin($fin, $response);
    }

    private function mockData(string $fin): array
    {
        $identity = new Identity([
            'PIN' => $fin,
            'DocumentSeria' => 'AA',
            'DocumentNumber' => '1234567',
            'Name' => 'Əli',
            'Surname' => 'Həsənov',
            'NameEn' => 'Ali',
            'SurnameEn' => 'Hasanov',
            'Patronymic' => 'Həsən oğlu',
            'BirthDate' => '01.01.1990',
            'BirthAddress' => 'Bakı şəhəri',
            'Gender' => 'Kişi',
            'RegistrationAddress' => 'Bakı şəhəri, Nərimanov rayonu, Əliağa Vahid küç., ev 5, mən 12',
            'GivenDate' => '01.01.2020',
            'ActivationDate' => '01.01.2020',
            'ExpireDate' => '01.01.2030',
            'MaritalStatus' => 'Evli',
            'GivenOrganization' => 'Azərbaycan Respublikasının Daxili İşlər Nazirliyi',
            'Citizenship' => 'Azərbaycan Respublikası',
            'Image' => null,
            'Sign' => null,
            'MilitaryStatus' => null,
            'BloodType' => 'A(II)',
            'EyeColor' => 'Qara',
            'Height' => 175,
        ]);

        return [
            'raw' => $identity->toArray(),
            'formatData' => $this->formatter->handle($identity),
        ];
    }
}
