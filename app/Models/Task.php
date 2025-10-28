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
        'start_at',
        'end_at',
        'status',
        'owner_id',
        'completed_at'
    ];

    public $casts = [
        'completed_at' => 'datetime',
        'start_at' => 'datetime',
        'end_at' => 'datetime'
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
