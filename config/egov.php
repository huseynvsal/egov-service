<?php

return [
    'update_after_days' => env('UPDATE_IDENTITY_AFTER_DAY', 7),
    'low_balance_threshold' => env('LOW_BALANCE_THRESHOLD', 500),
    'low_balance_emails' => array_filter(explode(',', env('LOW_BALANCE_NOTIFY_EMAILS', ''))),
    'mail_debug' => env('MAIL_DEBUG'),
];
