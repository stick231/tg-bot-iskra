<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    //
    public $fillable = [
        'title',
        'category',
        'remind_at',
        'status',
        'owner_id',
        'completed_at'
    ];

    public $casts = [
        'completed_at' => 'datetime',
        'remind_at' => 'datetime'
    ];
}
