<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HolidayCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'name',
        'type',
        'applies_to',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'applies_to' => 'array',
        ];
    }
}
