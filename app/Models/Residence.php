<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Residence extends Model
{
    protected $fillable = [
        'PIN', 'Name', 'Surname', 'DocumentNumber', 'DocumentType',
        'BirthDate', 'BirthAddress', 'Gender', 'RegistrationAddress',
        'ExpireDate', 'GivenDate', 'Citizenship', 'Image',
    ];

    protected $casts = [
        'RegistrationAddress' => 'array',
    ];
}
