<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollNotificationDelivery extends Model
{
    use HasFactory;

    public const TRIGGER_AUTO_PAID = 'auto_paid';

    public const TRIGGER_MANUAL_RESEND = 'manual_resend';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'payroll_id',
        'payroll_detail_id',
        'employee_id',
        'recipient_email',
        'channel',
        'trigger_type',
        'delivery_status',
        'failure_reason',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function payrollDetail()
    {
        return $this->belongsTo(PayrollDetail::class);
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }
}
