<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'column_id',
    'assignee_id',
    'title',
    'description',
    'due_date',
    'priority',
    'completed_at',
    'position',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'due_date' => 'date',
            'priority' => TaskPriority::class,
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class, 'column_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'label_task', 'task_id', 'label_id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<TaskChecklistItem, $this>
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<TaskDependency, $this>
     */
    public function outgoingDependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'dependent_task_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
