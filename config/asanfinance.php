<?php

return [
    'key' => env('ASAN_FINANCE_KEY'),
    'base_uri' => env('ASAN_FINANCE_BASE_URI', 'https://asanfinance.gov.az'),
    'timeout' => env('ASAN_FINANCE_REQUEST_TIMEOUT', 10),
    'verify_ssl_peer' => env('ASAN_FINANCE_VERIFY_SSL_PEER', false),
];
