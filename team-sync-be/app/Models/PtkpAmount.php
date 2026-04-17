<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PtkpAmount extends Model
{
    protected $fillable = [
        'status',
        'annual_amount',
    ];

    protected function casts(): array
    {
        return [
            'annual_amount' => 'decimal:2',
        ];
    }
}
