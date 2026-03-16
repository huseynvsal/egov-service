<?php

namespace App\Jobs;

use App\Models\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class WriteRequestLog implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $pin,
        public readonly int $type,
    ) {
        $this->onQueue('logs');
    }

    public function handle(): void
    {
        Log::create(['pin' => $this->pin, 'type' => $this->type]);
    }
}
