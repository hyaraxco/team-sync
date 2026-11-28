<?php

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class PerformanceAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()->hasPermissionTo('analytics-hr-view')) {
            return true;
        }

        $teamId = filter_var($this->input('team_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($teamId === false) {
            // Let validation report malformed or missing filters as 422, not authorization failures.
            return true;
        }

        $team = $this->user()->leadTeams()->find($teamId);
        if (! $team) {
            return false;
        }

        $staffMemberId = filter_var($this->input('staff_member_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $staffMemberId === false || $team->members()
            ->where('staff_member_id', $staffMemberId)->whereHas('staffMember')->exists();
    }

    public function rules(): array
    {
        if ($this->user()->hasPermissionTo('analytics-hr-view')) {
            return [];
        }

        return [
            'team_id' => ['required', 'integer', 'min:1'],
            'staff_member_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
