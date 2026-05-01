<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThrPayroll extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    // Religion event constants
    public const EVENT_IDUL_FITRI = 'idul_fitri';

    public const EVENT_NATAL = 'natal';

    public const EVENT_NYEPI = 'nyepi';

    public const EVENT_WAISAK = 'waisak';

    public const EVENT_IMLEK = 'imlek';

    /**
     * Minimum days before holiday that THR must be paid.
     */
    public const MIN_DAYS_BEFORE_HOLIDAY = 7;

    /**
     * Minimum tenure in months to be eligible for THR.
     */
    public const MIN_TENURE_MONTHS = 1;

    /**
     * Full THR threshold — employees with >= 12 months get full salary.
     */
    public const FULL_THR_TENURE_MONTHS = 12;

    /**
     * Mapping of religion to THR event.
     */
    public const RELIGION_EVENT_MAP = [
        'islam' => self::EVENT_IDUL_FITRI,
        'kristen' => self::EVENT_NATAL,
        'katolik' => self::EVENT_NATAL,
        'hindu' => self::EVENT_NYEPI,
        'budha' => self::EVENT_WAISAK,
        'konghucu' => self::EVENT_IMLEK,
    ];

    /**
     * Human-readable event labels.
     */
    public const EVENT_LABELS = [
        self::EVENT_IDUL_FITRI => 'Idul Fitri',
        self::EVENT_NATAL => 'Natal / Christmas',
        self::EVENT_NYEPI => 'Nyepi',
        self::EVENT_WAISAK => 'Waisak',
        self::EVENT_IMLEK => 'Imlek',
    ];

    protected $fillable = [
        'year',
        'religion_event',
        'religion_holiday_date',
        'payment_deadline',
        'payment_date',
        'status',
        'total_employees',
        'total_thr_amount',
        'total_tax_amount',
        'total_net_amount',
        'created_by',
        'approved_by',
        'approved_at',
        'notes',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'religion_holiday_date' => 'date',
            'payment_deadline' => 'date',
            'payment_date' => 'date',
            'total_thr_amount' => 'decimal:2',
            'total_tax_amount' => 'decimal:2',
            'total_net_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function details()
    {
        return $this->hasMany(ThrPayrollDetail::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    public static function eventForReligion(string $religion): ?string
    {
        return self::RELIGION_EVENT_MAP[strtolower($religion)] ?? null;
    }

    public static function eventLabel(string $event): string
    {
        return self::EVENT_LABELS[$event] ?? ucfirst(str_replace('_', ' ', $event));
    }

    public function getEventLabelAttribute(): string
    {
        return self::eventLabel($this->religion_event);
    }
}
