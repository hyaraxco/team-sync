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
            $settled = PayrollTaxEntry::query()->where('employee_id', $year->employee_id)
                ->where('period', 'like', $year->year.'-%')->whereNotNull('settled_at')->max('period');
            if ($settled !== null && $year->isDirty(['profile', 'history', 'provenance'])) {
                $original = $year->getOriginal('profile') ?? [];
                if (($year->getOriginal('history') ?? []) != $year->history) {
                    throw new PayrollStateException('Cannot replace imported history for a settled tax year.');
                }
                foreach (['employment_start_date', 'subjective_start_date'] as $field) {
                    if ($year->exists && ($original[$field] ?? null) !== ($year->profile[$field] ?? null)) {
                        throw new PayrollStateException('Cannot replace start dates for a settled tax year.');
                    }
                }
                foreach (['employment_end_date', 'subjective_end_date'] as $field) {
                    $before = $original[$field] ?? null;
                    $after = $year->profile[$field] ?? null;
                    if ($before !== $after && (($before !== null && substr($before, 0, 7) <= $settled)
                        || ($after !== null && substr($after, 0, 7) <= $settled))) {
                        throw new PayrollStateException('End-date changes cannot affect settled tax periods.');
                    }
                }
                if ($year->exists && $year->isDirty('provenance')) {
                    $previous = $year->getOriginal('provenance');
                    $year->provenance = str_ends_with($previous, "\n".$year->provenance)
                        ? $previous : $previous."\n".$year->provenance;
                }
            }
        });
        static::deleting(function () {
            throw new PayrollStateException('Imported tax history cannot be deleted.');
        });
    }
}
