<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Identity extends Model
{
    protected $fillable = [
        'PIN', 'DocumentSeria', 'DocumentNumber', 'Name', 'Surname',
        'NameEn', 'SurnameEn', 'Patronymic', 'BirthDate', 'BirthAddress',
        'Gender', 'RegistrationAddress', 'GivenDate', 'ActivationDate',
        'ExpireDate', 'MaritalStatus', 'GivenOrganization', 'Citizenship',
        'Image', 'Sign', 'MilitaryStatus', 'BloodType', 'EyeColor', 'Height',
    ];
}
