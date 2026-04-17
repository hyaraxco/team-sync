<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BpjsRate extends Model
{
    protected $fillable = [
        'component',
        'employee_rate',
        'employer_rate',
        'max_salary_base',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'employee_rate' => 'decimal:2',
            'employer_rate' => 'decimal:2',
            'max_salary_base' => 'decimal:2',
        ];
    }
}
