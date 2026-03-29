<?php

namespace App\Policies;

use App\Models\Column;
use App\Models\Project;
use App\Models\User;

class ColumnPolicy
{
    /**
     * Create a column within a project (same privilege as updating the project board).
     */
    public function create(User $user, Project $project): bool
    {
        return $user->can('update', $project);
    }

    public function update(User $user, Column $column): bool
    {
        return $user->can('update', $column->project);
    }

    public function delete(User $user, Column $column): bool
    {
        return $user->can('update', $column->project);
    }
}
