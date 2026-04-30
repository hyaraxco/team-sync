<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollApprovalPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'min_amount',
        'max_amount',
        'required_role',
        'approval_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'approval_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function approvals()
    {
        return $this->hasMany(PayrollApproval::class, 'policy_id');
    }

    /**
     * Get active policies that apply to a given total amount.
     */
    public static function getApplicablePolicies(float $totalAmount): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->where('is_active', true)
            ->where('min_amount', '<=', $totalAmount)
            ->where(function ($query) use ($totalAmount) {
                $query->whereNull('max_amount')
                    ->orWhere('max_amount', '>=', $totalAmount);
            })
            ->orderBy('approval_order')
            ->get();
    }
}
