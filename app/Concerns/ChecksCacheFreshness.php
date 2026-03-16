<?php

namespace App\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

trait ChecksCacheFreshness
{
    private function isFresh(Model $record): bool
    {
        $ttlDays = config('egov.update_after_days', 7);

        if ($record->updated_at->diffInDays(now()) >= $ttlDays) {
            return false;
        }

        if ($record->ExpireDate) {
            return ! Carbon::createFromFormat('d.m.Y', $record->ExpireDate)->isPast();
        }

        return true;
    }
}
