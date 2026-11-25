<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollBpjsPeriod extends Model
{
    protected $fillable = ['contribution_month', 'facts'];

    protected function casts(): array
    {
        return ['facts' => 'array'];
    }
}
