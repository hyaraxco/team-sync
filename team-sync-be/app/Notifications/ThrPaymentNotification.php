<?php

namespace App\Notifications;

use App\Models\ThrPayroll;
use App\Models\ThrPayrollDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ThrPaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ThrPayroll $thrPayroll,
        private readonly ThrPayrollDetail $detail
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $eventLabel = ThrPayroll::eventLabel($this->thrPayroll->religion_event);

        return [
            'type' => 'thr_payment',
            'category' => 'payroll',
            'title' => "THR {$eventLabel} {$this->thrPayroll->year} Dibayarkan",
            'body' => "THR {$eventLabel} Anda sebesar Rp ".number_format((float) $this->detail->net_thr_amount, 0, ',', '.').' telah dibayarkan.',
            'action_url' => '/admin/payroll/thr',
            'thr_payroll_id' => $this->thrPayroll->id,
            'thr_detail_id' => $this->detail->id,
            'religion_event' => $this->thrPayroll->religion_event,
            'year' => $this->thrPayroll->year,
            'gross_amount' => (float) $this->detail->gross_thr_amount,
            'tax_amount' => (float) $this->detail->pph21_amount,
            'net_amount' => (float) $this->detail->net_thr_amount,
            'payment_date' => $this->thrPayroll->payment_date?->format('Y-m-d'),
        ];
    }
}
