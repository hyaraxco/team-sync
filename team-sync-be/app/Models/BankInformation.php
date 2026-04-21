<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankInformation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'bank_name',
        'account_number',
        'account_holder_name',
    ];

    public function staffMember()
    {
        return $this->belongsTo(StaffMemberProfile::class, 'employee_id');
    }
}
