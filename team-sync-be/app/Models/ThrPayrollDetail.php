<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThrPayrollDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'thr_payroll_id',
        'staff_member_id',
        'religion',
        'monthly_salary',
        'join_date',
        'tenure_months',
        'proration_factor',
        'gross_thr_amount',
        'pph21_amount',
        'net_thr_amount',
        'ptkp_status',
        'has_npwp',
        'tax_calculation_meta',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
            'tenure_months' => 'integer',
            'proration_factor' => 'decimal:4',
            'gross_thr_amount' => 'decimal:2',
            'pph21_amount' => 'decimal:2',
            'net_thr_amount' => 'decimal:2',
            'has_npwp' => 'boolean',
            'join_date' => 'date',
            'tax_calculation_meta' => 'array',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function thrPayroll()
    {
        return $this->belongsTo(ThrPayroll::class);
    }

    public function staffMember()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'staff_member_id');
    }
}
