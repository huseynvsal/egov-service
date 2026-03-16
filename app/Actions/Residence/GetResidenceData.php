<?php

namespace App\Actions\Residence;

use App\Contracts\LogRepositoryInterface;
use App\Contracts\ResidenceRepositoryInterface;
use App\Models\Log;
use App\Models\Residence;
use App\Services\AsanFinanceService;

class GetResidenceData
{
    public function __construct(
        private readonly AsanFinanceService $asanFinance,
        private readonly ResidenceRepositoryInterface $residenceRepo,
        private readonly LogRepositoryInterface $logRepo,
        private readonly FormatResidenceData $formatter,
    ) {
    }

    public function handle(string $fin): array
    {
        $cached = $this->residenceRepo->findByPin($fin);

        if ($cached && $this->isFresh($cached)) {
            $residence = $cached;
        } else {
            $apiData = $this->asanFinance->getResidenceInfo($fin);
            $response = $apiData['Response'];

            $residence = $this->residenceRepo->upsertByPin($fin, $response);
        }

        $this->logRepo->add($fin, Log::TYPE_RESIDENCE);

        return [
            'raw' => $residence->toArray(),
            'formatData' => $this->formatter->handle($residence),
        ];
    }

    private function isFresh(Residence $residence): bool
    {
        $ttlDays = config('egov.update_after_days', 7);

        if ($residence->updated_at->diffInDays(now()) >= $ttlDays) {
            return false;
        }

        if ($residence->ExpireDate) {
            $expiry = \DateTime::createFromFormat('d.m.Y', $residence->ExpireDate);
            if ($expiry && $expiry < now()) {
                return false;
            }
        }

        return true;
    }
}
