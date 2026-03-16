<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    public const int TYPE_PERSONAL = 1;
    public const int TYPE_EMPLOYEE = 2;
    public const int TYPE_RESIDENCE = 3;

    protected $fillable = ['pin', 'type'];
}
