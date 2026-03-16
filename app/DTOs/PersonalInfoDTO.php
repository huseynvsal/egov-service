<?php

namespace App\DTOs;

readonly class PersonalInfoDTO
{
    public function __construct(
        public string $fin,
        public ?string $docNumber = null,
    ) {}
}
