<?php

namespace App\Models;

use App\Support\SensitiveData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankInformation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'staff_member_id',
        'bank_name',
        'account_number',
        'account_number_hash',
        'account_holder_name',
    ];

    protected function casts(): array
    {
        return [
            'bank_name' => 'encrypted',
            'account_number' => 'encrypted',
            'account_holder_name' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $bankInformation) {
            $bankInformation->account_number_hash = SensitiveData::hash($bankInformation->account_number);
        });
    }

    public function staffMember()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'staff_member_id');
    }
}
