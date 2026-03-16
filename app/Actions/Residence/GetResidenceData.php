<?php

namespace App\Actions\Residence;

use App\Concerns\ChecksCacheFreshness;
use App\Contracts\LogRepositoryInterface;
use App\Contracts\ResidenceRepositoryInterface;
use App\Models\Log;
use App\Models\Residence;
use App\Services\AsanFinanceService;

class GetResidenceData
{
    use ChecksCacheFreshness;

    public function __construct(
        private readonly AsanFinanceService $asanFinance,
        private readonly ResidenceRepositoryInterface $residenceRepo,
        private readonly LogRepositoryInterface $logRepo,
        private readonly FormatResidenceData $formatter,
    ) {
    }

    public function handle(string $fin): array
    {
        $residence = $this->resolveResidence($fin);

        $this->logRepo->add($fin, Log::TYPE_RESIDENCE);

        return [
            'raw' => $residence->toArray(),
            'formatData' => $this->formatter->handle($residence),
        ];
    }

    private function resolveResidence(string $fin): Residence
    {
        $cached = $this->residenceRepo->findByPin($fin);

        if ($cached && $this->isFresh($cached)) {
            return $cached;
        }

        $data = $this->asanFinance->getResidenceInfo($fin);

        return $this->residenceRepo->upsertByPin($fin, $data['Response']);
    }
}
