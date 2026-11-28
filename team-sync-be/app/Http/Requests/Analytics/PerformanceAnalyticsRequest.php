<?php

namespace App\Http\Requests\Analytics;

use App\Interfaces\AnalyticsRepositoryInterface;
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

        $staffMemberId = $this->input('staff_member_id');
        if ($staffMemberId !== null) {
            $staffMemberId = filter_var($staffMemberId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($staffMemberId === false) {
                return true;
            }
        }

        return app(AnalyticsRepositoryInterface::class)->managerCanReadPerformanceScope(
            (int) $this->user()->getAuthIdentifier(), $teamId, $staffMemberId
        );
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
