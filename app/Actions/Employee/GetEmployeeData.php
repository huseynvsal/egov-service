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

        $cached = $this->employeeRepo->findByPin($fin);

        if ($cached && $this->isFresh($cached)) {
            return $cached->employee_data;
        }

        $apiData = $this->asanFinance->getEmployeeInfo($fin);
        $response = $apiData['Response'];

        $this->employeeRepo->upsertByPin($fin, ['employee_data' => $response]);
        $this->logRepo->add($fin, Log::TYPE_EMPLOYEE);

        return $response;
    }

    private function isFresh(Employee $employee): bool
    {
        $ttlDays = config('egov.update_after_days', 7);

        return $employee->updated_at->diffInDays(now()) < $ttlDays;
    }

    private function mockData(string $fin): array
    {
        $faker = FakerFactory::create('az_AZ');

        return [
            'PIN' => $fin,
            'Name' => $faker->firstName(),
            'Surname' => $faker->lastName(),
            'Employer' => $faker->company(),
            'Position' => $faker->jobTitle(),
            'StartDate' => $faker->dateTimeBetween('-5 years', '-1 year')->format('d.m.Y'),
            'EndDate' => null,
            'EmployerTin' => $faker->numerify('##########'),
        ];
    }
}
