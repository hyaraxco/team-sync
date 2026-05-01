<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'logo_url',
        'timezone',
        'locale',
        'currency',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Get the current (default) company.
     * In single-tenant mode, this returns the first company.
     */
    public static function current(): ?self
    {
        return static::query()->first();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function staffMemberProfiles(): HasMany
    {
        return $this->hasMany(StaffMemberProfile::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function attendancePeriods(): HasMany
    {
        return $this->hasMany(AttendancePeriod::class);
    }

    public function payrollSettings(): HasMany
    {
        return $this->hasMany(PayrollSetting::class);
    }
}
