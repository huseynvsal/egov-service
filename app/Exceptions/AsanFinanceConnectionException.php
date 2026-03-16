<?php

namespace App\Exceptions;

class AsanFinanceConnectionException extends EgovException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 503);
    }
}
