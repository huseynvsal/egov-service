<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;
    protected $fillable = ['pin', 'employee_data'];

    protected $casts = [
        'employee_data' => 'array',
    ];
}
