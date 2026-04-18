<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PayrollSetting extends Model
{
    use HasFactory;

    public const VERSIONED_FIELDS = [
        'payday_day',
        'attendance_cutoff_day',
        'working_days_mode',
        'default_working_days',
        'absent_deduction_rate',
        'rounding_mode',
        'rounding_unit',
        'note_template',
    ];

    public const DEFAULT_NOTE_TEMPLATE = 'Hari kerja: {working_days} | Hadir: {attended_days} | Terlambat: {late_days} | Sakit: {sick_days} | Izin: {permission_days} | Alpha: {absent_days} | Potongan: Rp {deduction}';

    protected $fillable = [
        'payday_day',
        'attendance_cutoff_day',
        'working_days_mode',
        'default_working_days',
        'absent_deduction_rate',
        'rounding_mode',
        'rounding_unit',
        'note_template',
        'payroll_bank_name',
        'payroll_bank_code',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'absent_deduction_rate' => 'decimal:2',
        ];
    }

    public static function defaults(): array
    {
        return [
            'payday_day' => 25,
            'attendance_cutoff_day' => 25,
            'working_days_mode' => 'auto_business_days',
            'default_working_days' => 22,
            'absent_deduction_rate' => 1.00,
            'rounding_mode' => 'nearest',
            'rounding_unit' => 1000,
            'note_template' => self::DEFAULT_NOTE_TEMPLATE,
            'payroll_bank_name' => null,
            'payroll_bank_code' => null,
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], self::defaults());
    }

    public function resolveActiveVersion(?int $actorId = null): PayrollSettingVersion
    {
        return DB::transaction(function () use ($actorId) {
            /** @var PayrollSettingVersion|null $latestVersion */
            $latestVersion = $this->versions()
                ->lockForUpdate()
                ->first();

            if (! $latestVersion || $this->hasVersionMismatch($latestVersion)) {
                return $this->versions()->create([
                    ...$this->toVersionAttributes(),
                    'version_number' => (int) ($latestVersion?->version_number ?? 0) + 1,
                    'effective_at' => now(),
                    'updated_by' => $actorId ?? $this->updated_by,
                ]);
            }

            return $latestVersion;
        });
    }

    public function versions()
    {
        return $this->hasMany(PayrollSettingVersion::class, 'payroll_setting_id')
            ->orderByDesc('version_number');
    }

    public function latestVersion()
    {
        return $this->hasOne(PayrollSettingVersion::class, 'payroll_setting_id')
            ->latestOfMany('version_number');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return array<string, float|int|string>
     */
    private function toVersionAttributes(): array
    {
        return [
            'payday_day' => (int) $this->payday_day,
            'attendance_cutoff_day' => (int) $this->attendance_cutoff_day,
            'working_days_mode' => (string) $this->working_days_mode,
            'default_working_days' => (int) $this->default_working_days,
            'absent_deduction_rate' => (float) $this->absent_deduction_rate,
            'rounding_mode' => (string) $this->rounding_mode,
            'rounding_unit' => (int) $this->rounding_unit,
            'note_template' => filled($this->note_template)
                ? trim((string) $this->note_template)
                : self::DEFAULT_NOTE_TEMPLATE,
        ];
    }

    private function hasVersionMismatch(PayrollSettingVersion $latestVersion): bool
    {
        $currentSnapshot = $this->toVersionAttributes();

        foreach (self::VERSIONED_FIELDS as $field) {
            if ($this->normalizeSnapshotValue($field, $currentSnapshot[$field])
                !== $this->normalizeSnapshotValue($field, $latestVersion->{$field})) {
                return true;
            }
        }

        return false;
    }

    private function normalizeSnapshotValue(string $field, mixed $value): string
    {
        if ($field === 'absent_deduction_rate') {
            return number_format((float) $value, 2, '.', '');
        }

        if (is_string($value)) {
            return trim($value);
        }

        return (string) $value;
    }
}
