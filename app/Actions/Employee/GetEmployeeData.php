<?php

namespace App\Actions\Employee;

use App\Contracts\EmployeeRepositoryInterface;
use App\Contracts\LogRepositoryInterface;
use App\Models\Employee;
use App\Models\Log;
use App\Services\AsanFinanceService;
use Faker\Factory as FakerFactory;

class GetEmployeeData
{
    public function __construct(
        private readonly AsanFinanceService $asanFinance,
        private readonly EmployeeRepositoryInterface $employeeRepo,
        private readonly LogRepositoryInterface $logRepo,
    ) {
    }

    public function handle(string $fin): array
    {
        if (config('app.debug') && config('app.env') === 'development') {
            return $this->mockData($fin);
        }

        $data = $this->resolveEmployeeData($fin);

        $this->logRepo->add($fin, Log::TYPE_EMPLOYEE);

        return $data;
    }

    private function resolveEmployeeData(string $fin): array
    {
        $cached = $this->employeeRepo->findByPin($fin);

        if ($cached && $this->isFresh($cached)) {
            return $cached->employee_data;
        }

        return $this->fetchAndStore($fin);
    }

    private function fetchAndStore(string $fin): array
    {
        $response = $this->asanFinance->getEmployeeInfo($fin)['Response'];

        $this->employeeRepo->upsertByPin($fin, ['employee_data' => $response]);

        return $response;
    }

    private function isFresh(Employee $employee): bool
    {
        return $employee->updated_at->diffInDays(now()) < config('egov.update_after_days', 7);
    }

    private function mockData(string $fin): array
    {
        $faker = FakerFactory::create('az_AZ');

        $isMale = $faker->randomElement([true, false]);
        $name = $isMale ? $faker->firstNameMale : $faker->firstNameFemale;
        $surname = $faker->lastName;
        $patronymic = $isMale
            ? $faker->firstNameMale . ' OĞLU'
            : $faker->firstNameMale . ' QIZI';

        $positions = [
            'Tərtibatçı',
            'Baş Mühəndis',
            'Proqram Təminatı Mütəxəssisi',
            'Front-end Tərtibatçısı',
            'Back-end Tərtibatçısı',
            'Mobil Tətbiqlər Mütəxəssisi',
        ];

        $departments = [
            'İnformasiya Texnologiyaları Departamenti',
            'Proqram Təminatı Şöbəsi',
            'Sistem İdarəetmə Şöbəsi',
            'Rəqəmsal Həllər Departamenti',
        ];

        $rays = ['NƏSİMİ', 'XƏTAİ', 'SƏBAİL'];

        return [
            'Active' => [
                [
                    'Contract' => [
                        'Number' => $fin . '000700',
                        'Status' => ['Label' => '1', 'Description' => 'Qüvvədədir'],
                        'EndDate' => null,
                        'SignDate' => date('d.m.Y', strtotime('-2 months')),
                        'BeginDate' => date('d.m.Y', strtotime('-2 months')),
                        'InsertDate' => date('d.m.Y', strtotime('-2 months')),
                        'PeriodType' => ['Label' => '0', 'Description' => 'Müddətsiz'],
                        'NextEndDate' => null,
                        'Invalidation' => ['Label' => '0', 'Description' => 'Etibarlı'],
                    ],
                    'Employee' => [
                        'SSN' => $faker->numerify('#############'),
                        'Name' => mb_strtoupper($name),
                        'Phone' => '994' . $faker->numerify('#########'),
                        'Salary' => $faker->numberBetween(2000, 5000),
                        'Surname' => mb_strtoupper($surname),
                        'Position' => $faker->randomElement($positions),
                        'WorkPlace' => 'Baş ofis/' . $faker->randomElement($departments) . '/' . $faker->randomElement($departments),
                        'Patronymic' => mb_strtoupper($patronymic),
                        'WorkPlaceType' => ['Label' => '1', 'Description' => 'Əsas'],
                        'WorkCasualType' => ['Label' => '2', 'Description' => 'Vaxtamuzd'],
                        'PositionLabourContract' => $faker->randomElement($positions) . ' üzrə ' . ($isMale ? 'Mühəndis' : 'Mütəxəssis'),
                    ],
                    'Employer' => [
                        'Name' => mb_strtoupper($faker->company . ' MƏHDUD MƏSULİYYƏTLİ CƏMİYYƏTİ'),
                        'Voen' => $faker->numerify('##########'),
                        'Phone' => '994' . $faker->numerify('#########'),
                        'WorkerCount' => $faker->numberBetween(50, 200),
                        'LegalAddress' => 'AZ' . $faker->numerify('####') . ', BAKI ŞƏHƏRİ ' . $faker->randomElement($rays) . ' RAYONU, ' . $faker->streetName . ', ev ' . $faker->numberBetween(1, 100),
                        'PropertyType' => ['Label' => '3', 'Description' => 'Xüsusi mülkiyyət'],
                    ],
                ],
            ],
            'Deactive' => [
                [
                    'Contract' => [
                        'EndDate' => null,
                        'BeginDate' => date('d.m.Y', strtotime('-2 years')),
                        'TerminateDate' => date('d.m.Y', strtotime('-6 months')),
                    ],
                    'Employee' => [
                        'Salary' => $faker->numberBetween(1500, 3000),
                        'Position' => $faker->randomElement($positions),
                    ],
                    'Employer' => [
                        'Name' => mb_strtoupper($faker->company . ' MƏHDUD MƏSULİYYƏTLİ CƏMİYYƏTİ'),
                        'Voen' => $faker->numerify('##########'),
                    ],
                ],
                [
                    'Contract' => [
                        'EndDate' => null,
                        'BeginDate' => date('d.m.Y', strtotime('-4 years')),
                        'TerminateDate' => date('d.m.Y', strtotime('-2 years')),
                    ],
                    'Employee' => [
                        'Salary' => $faker->numberBetween(1000, 2000),
                        'Position' => $faker->randomElement($positions),
                    ],
                    'Employer' => [
                        'Name' => mb_strtoupper($faker->company . ' MƏHDUD MƏSULİYYƏTLİ CƏMİYYƏTİ'),
                        'Voen' => $faker->numerify('##########'),
                    ],
                ],
            ],
        ];
    }
}
