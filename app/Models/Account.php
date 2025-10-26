<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'card_number'
    ];

    protected $hidden = [];

    // Si no quieres timestamps, agrega:
    // public $timestamps = false;
}