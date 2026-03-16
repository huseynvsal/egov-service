<?php

namespace App\Repositories;

use App\Contracts\LogRepositoryInterface;
use App\Models\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LogRepository implements LogRepositoryInterface
{
    public function add(string $pin, int $type): void
    {
        Log::create(['pin' => $pin, 'type' => $type]);
    }

    public function yearlyReport(): Collection
    {
        return DB::table('logs')
            ->selectRaw('YEAR(created_at) as request_year, MONTH(created_at) as request_month')
            ->selectRaw('SUM(CASE WHEN type = 1 THEN 1 ELSE 0 END) as personal_requests')
            ->selectRaw('SUM(CASE WHEN type = 2 THEN 1 ELSE 0 END) as employment_requests')
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at), MONTH(created_at)')
            ->get();
    }
}
