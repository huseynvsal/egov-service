<?php

namespace App\Actions\Identity;

use App\Contracts\IdentityRepositoryInterface;
use App\Contracts\LogRepositoryInterface;
use App\Models\Identity;
use App\Models\Log;
use App\Services\AsanFinanceService;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;

class GetPersonalData
{
    public function __construct(
        private readonly AsanFinanceService $asanFinance,
        private readonly IdentityRepositoryInterface $identityRepo,
        private readonly LogRepositoryInterface $logRepo,
        private readonly FormatIdentityData $formatter,
    ) {}

    public function handle(string $fin, ?string $docNumber = null): array
    {
        if (config('app.debug') && config('app.env') === 'development') {
            return $this->mockData($fin);
        }

        $cached = $this->identityRepo->findByPin($fin);

        if ($cached && $this->isFresh($cached)) {
            $identity = $cached;
        } else {
            $apiData = $docNumber
                ? $this->asanFinance->getPersonalInfoByFinAndDoc($fin, $docNumber)
                : $this->asanFinance->getPersonalInfoByFin($fin);

            $response = $apiData['Response'];

            if (empty($response['ExpireDate'])) {
                $birthDate            = Carbon::createFromFormat('d.m.Y', $response['BirthDate']);
                $response['ExpireDate'] = $birthDate->addYears(100)->format('d.m.Y');
            }

            $identity = $this->identityRepo->upsertByPin($fin, $response);
        }

        $this->logRepo->add($fin, Log::TYPE_PERSONAL);

        return [
            'raw'        => $identity->toArray(),
            'formatData' => $this->formatter->handle($identity),
        ];
    }

    private function isFresh(Identity $identity): bool
    {
        $ttlDays = config('egov.update_after_days', 7);

        if ($identity->updated_at->diffInDays(now()) >= $ttlDays) {
            return false;
        }

        if ($identity->ExpireDate) {
            $expiry = \DateTime::createFromFormat('d.m.Y', $identity->ExpireDate);
            if ($expiry && $expiry < now()) {
                return false;
            }
        }

        return true;
    }

    private function mockData(string $fin): array
    {
        $faker = FakerFactory::create('az_AZ');

        $birthDate  = $faker->dateTimeBetween('-60 years', '-18 years')->format('d.m.Y');
        $givenDate  = $faker->dateTimeBetween('-10 years', '-1 year')->format('d.m.Y');
        $expireDate = $faker->dateTimeBetween('+1 year', '+10 years')->format('d.m.Y');

        $identity = new Identity([
            'PIN'                 => $fin,
            'DocumentSeria'       => 'AA',
            'DocumentNumber'      => $faker->numerify('#######'),
            'Name'                => $faker->firstName(),
            'Surname'             => $faker->lastName(),
            'NameEn'              => $faker->firstName(),
            'SurnameEn'           => $faker->lastName(),
            'Patronymic'          => $faker->lastName() . ' oğlu',
            'BirthDate'           => $birthDate,
            'BirthAddress'        => 'Bakı şəhəri',
            'Gender'              => 'Kişi',
            'RegistrationAddress' => 'Bakı şəhəri, Nərimanov rayonu, Əliağa Vahid küç., ev 5, mən 12',
            'GivenDate'           => $givenDate,
            'ActivationDate'      => $givenDate,
            'ExpireDate'          => $expireDate,
            'MaritalStatus'       => 'Evli',
            'GivenOrganization'   => 'Azərbaycan Respublikasının Daxili İşlər Nazirliyi',
            'Citizenship'         => 'Azərbaycan Respublikası',
            'Image'               => null,
            'Sign'                => null,
            'MilitaryStatus'      => null,
            'BloodType'           => 'A(II)',
            'EyeColor'            => 'Qara',
            'Height'              => 175,
        ]);

        return [
            'raw'        => $identity->toArray(),
            'formatData' => $this->formatter->handle($identity),
        ];
    }
}
