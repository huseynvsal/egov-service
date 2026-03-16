<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = ['pin', 'employee_data'];

    protected $casts = [
        'employee_data' => 'array',
    ];
}
