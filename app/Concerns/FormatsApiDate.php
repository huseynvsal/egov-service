<?php

namespace App\Concerns;

trait FormatsApiDate
{
    private function formatDate(string $date): string
    {
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $date, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return $date;
    }
}
