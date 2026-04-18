<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDependency extends Model
{
    protected $fillable = [
        'dependent_task_id',
        'prerequisite_task_id',
    ];

    public function dependent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'dependent_task_id');
    }

    public function prerequisite(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'prerequisite_task_id');
    }
}
