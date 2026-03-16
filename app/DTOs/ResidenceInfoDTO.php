<?php

namespace App\DTOs;

readonly class ResidenceInfoDTO
{
    public function __construct(
        public string $fin,
    ) {}
}
