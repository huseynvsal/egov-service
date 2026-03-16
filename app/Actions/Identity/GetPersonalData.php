<?php

namespace App\Actions\Identity;

use App\Concerns\ChecksCacheFreshness;
use App\Contracts\IdentityRepositoryInterface;
use App\Contracts\LogRepositoryInterface;
use App\Models\Identity;
use App\Models\Log;
use App\Services\AsanFinanceService;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;

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
        $faker = FakerFactory::create('az_AZ');

        $birthDate = $faker->dateTimeBetween('-60 years', '-18 years')->format('d.m.Y');
        $givenDate = $faker->dateTimeBetween('-10 years', '-1 year')->format('d.m.Y');
        $expireDate = $faker->dateTimeBetween('+1 year', '+10 years')->format('d.m.Y');

        $identity = new Identity([
            'PIN' => $fin,
            'DocumentSeria' => 'AA',
            'DocumentNumber' => $faker->numerify('#######'),
            'Name' => $faker->firstName(),
            'Surname' => $faker->lastName(),
            'NameEn' => $faker->firstName(),
            'SurnameEn' => $faker->lastName(),
            'Patronymic' => $faker->lastName() . ' oğlu',
            'BirthDate' => $birthDate,
            'BirthAddress' => 'Bakı şəhəri',
            'Gender' => 'Kişi',
            'RegistrationAddress' => 'Bakı şəhəri, Nərimanov rayonu, Əliağa Vahid küç., ev 5, mən 12',
            'GivenDate' => $givenDate,
            'ActivationDate' => $givenDate,
            'ExpireDate' => $expireDate,
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
