<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    public const TYPE_PERSONAL = 1;
    public const TYPE_EMPLOYEE = 2;
    public const TYPE_RESIDENCE = 3;

    protected $fillable = ['pin', 'type'];
}
