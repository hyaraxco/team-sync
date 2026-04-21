<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HybridWorkSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_member_id',
        'effective_from',
        'effective_until',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function staffMember()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'staff_member_id');
    }
}
