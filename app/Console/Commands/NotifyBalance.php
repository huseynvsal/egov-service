<?php

namespace App\Console\Commands;

use App\Mail\LowBalanceMail;
use App\Services\AsanFinanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotifyBalance extends Command
{
    protected $signature = 'notify:balance';
    protected $description = 'Check AsanFinance balance and notify if low';

    public function handle(AsanFinanceService $service): void
    {
        $response = $service->getBalance();
        $balance = $response['Response'][0]['Balance'] ?? 0;

        if ($balance <= config('egov.low_balance_threshold', 500)) {
            $emails = config('egov.low_balance_emails', []);

            if (config('app.debug')) {
                $emails = [config('egov.mail_debug')];
            }

            foreach (array_filter($emails) as $email) {
                Mail::to($email)->send(new LowBalanceMail($balance));
            }
        }
    }
}
