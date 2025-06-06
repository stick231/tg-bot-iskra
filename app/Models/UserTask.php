<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTask extends Model
{
    //
    protected $fillable = [
        'task_id',
        'user_id',
        'status',
        'completed_at'
    ];

    public function task() : BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
    
    public function users(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
