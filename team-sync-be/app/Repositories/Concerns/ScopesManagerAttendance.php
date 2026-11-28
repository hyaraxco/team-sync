<?php

namespace App\Repositories\Concerns;

use App\Models\JobInformation;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait ScopesManagerAttendance
{
    private function scopeAttendanceQueryForManager(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user && $user->hasRole('manager') && ! $user->hasAnyRole(['hr', 'superadmin'])) {
            $query->whereIn('staff_member_id', $this->getManageableEmployeeIdsForManager());
        }

        return $query;
    }

    private function getManageableEmployeeIdsForManager(): array
    {
        $user = Auth::user();

        if (! $user || ! $user->hasRole('manager')) {
            return [];
        }

        $leadTeamIds = Team::query()->where('team_lead_id', $user->id)->pluck('id');

        if ($leadTeamIds->isEmpty()) {
            return [];
        }

        $fromTeamMembers = TeamMember::query()
            ->whereIn('team_id', $leadTeamIds)
            ->whereNull('left_at')
            ->pluck('staff_member_id');

        $fromJobInformation = JobInformation::query()
            ->whereIn('team_id', $leadTeamIds)
            ->pluck('staff_member_id');

        return $fromTeamMembers->merge($fromJobInformation)->unique()->values()->all();
    }
}
