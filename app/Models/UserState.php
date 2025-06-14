<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserState extends Model
{
    protected $fillable = [
        'telegram_id',
        'state',
        'trigger_command',
        'waiting_for',
        'data'
    ];  

    protected $casts = [
        'data' => 'array',
    ];
}
