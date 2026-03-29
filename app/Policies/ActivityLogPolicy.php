<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;

class ActivityLogPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->can('view', $project);
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        return $user->can('view', $activityLog->project);
    }
}
