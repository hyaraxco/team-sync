<?php

namespace App\Models;

use App\Exceptions\PayrollStateException;
use Illuminate\Database\Eloquent\Model;

class PayrollTaxYear extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'profile' => 'array', 'history' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $year) {
            if ($year->exists && $year->isDirty(['employee_id', 'year'])) {
                throw new PayrollStateException('Tax year identity is immutable.');
            }
            if ($year->isDirty(['profile', 'history', 'provenance']) && PayrollTaxEntry::query()
                ->where('employee_id', $year->employee_id)->where('period', 'like', $year->year.'-%')
                ->whereNotNull('settled_at')->exists()) {
                throw new PayrollStateException('Cannot replace facts/history for a settled tax year.');
            }
        });
        static::deleting(function () {
            throw new PayrollStateException('Imported tax history cannot be deleted.');
        });
    }
}
