<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Residence extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'PIN', 'Name', 'Surname', 'DocumentNumber', 'DocumentType',
        'BirthDate', 'BirthAddress', 'Gender', 'RegistrationAddress',
        'ExpireDate', 'GivenDate', 'Citizenship', 'Image',
    ];

    protected $casts = [
        'RegistrationAddress' => 'array',
    ];
}
