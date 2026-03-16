<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Identity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'PIN', 'DocumentSeria', 'DocumentNumber', 'Name', 'Surname',
        'NameEn', 'SurnameEn', 'Patronymic', 'BirthDate', 'BirthAddress',
        'Gender', 'RegistrationAddress', 'GivenDate', 'ActivationDate',
        'ExpireDate', 'MaritalStatus', 'GivenOrganization', 'Citizenship',
        'Image', 'Sign', 'MilitaryStatus', 'BloodType', 'EyeColor', 'Height',
    ];
}
