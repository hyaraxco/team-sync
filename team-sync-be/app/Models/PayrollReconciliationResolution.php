<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollReconciliationResolution extends Model
{
    protected $fillable = [
        'payroll_id',
        'staff_member_id',
        'resolved_by',
        'exception_type',
        'resolution_action',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMemberProfile::class, 'staff_member_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
