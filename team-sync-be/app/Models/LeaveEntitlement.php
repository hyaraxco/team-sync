<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveEntitlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'employment_type',
        'leave_type',
        'is_eligible',
        'is_paid',
        'quota_scope',
        'quota_days',
        'carry_over_max_days',
        'requires_attachment',
        'requires_reason',
        'allowed_mime_types',
        'max_attachment_size_kb',
    ];

    protected function casts(): array
    {
        return [
            'is_eligible' => 'boolean',
            'is_paid' => 'boolean',
            'quota_days' => 'decimal:2',
            'requires_attachment' => 'boolean',
            'requires_reason' => 'boolean',
            'allowed_mime_types' => 'array',
        ];
    }
}
