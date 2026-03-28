<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'owner_id'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * Scope teams the user can access (owner or member).
     */
    public function scopeAccessibleByUser(Builder $query, User $user): void
    {
        $query->where(function (Builder $inner) use ($user): void {
            $inner->where('owner_id', $user->id)
                ->orWhereHas('members', function (Builder $members) use ($user): void {
                    $members->where('user_id', $user->id);
                });
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function membershipFor(User $user): ?TeamMember
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->first();
    }
}
