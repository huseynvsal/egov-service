<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = ['num_code', 'alpha_2', 'alpha_3', 'country_name', 'dialing_code', 'status'];
}
