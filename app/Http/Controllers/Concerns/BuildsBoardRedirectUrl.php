<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\Request;

trait BuildsBoardRedirectUrl
{
    /**
     * @return non-empty-string
     */
    protected function boardUrlWithFilters(Team $team, Project $project, Request $request): string
    {
        $query = collect($request->only([
            'search',
            'filter_column',
            'filter_assignee',
            'filter_label',
            'filter_priority',
            'filter_due',
        ]))
            ->filter(static fn (mixed $v): bool => $v !== null && $v !== '')
            ->all();

        $url = route('teams.projects.board', [$team, $project], false);

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }
}
