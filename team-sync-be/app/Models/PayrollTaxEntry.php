<?php

namespace App\Models;

use App\Exceptions\PayrollStateException;
use Illuminate\Database\Eloquent\Model;

class PayrollTaxEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'calculation' => 'array',
            'actual_withholding' => 'decimal:0',
            'active' => 'boolean',
            'recognized_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $entry) {
            if ($entry->getRawOriginal('settled_at') !== null) {
                throw new PayrollStateException('Settled tax entries are immutable; record a correction source.');
            }
            if ($entry->isDirty(['employee_id', 'source_type', 'source_id'])) {
                throw new PayrollStateException('Tax source identity is immutable.');
            }
        });
        static::deleting(function (self $entry) {
            throw new PayrollStateException('Tax entries cannot be deleted; deactivate an unsettled source.');
        });
    }
}
