<?php

namespace App\Notifications;

use App\Models\PayrollDetail;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayrollCorrected extends Notification implements ShouldQueue
{
    use Queueable;

    protected PayrollDetail $payrollDetail;

    protected int $correctionCount;

    public function __construct(PayrollDetail $payrollDetail, int $correctionCount)
    {
        $this->payrollDetail = $payrollDetail;
        $this->correctionCount = $correctionCount;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payroll = $this->payrollDetail->payroll;
        $salaryMonth = Carbon::parse($payroll->salary_month)->format('F Y');
        $paymentDate = Carbon::parse($payroll->payment_date)->format('d F Y');
        $originalSalary = (float) $this->payrollDetail->original_salary;
        $finalSalary = (float) $this->payrollDetail->final_salary;
        $deductionAmount = $originalSalary - $finalSalary;

        return (new MailMessage)
            ->subject('Koreksi Slip Gaji '.$salaryMonth.' (Koreksi #'.$this->correctionCount.')')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Gaji untuk periode **'.$salaryMonth.'** telah dikoreksi dan dibayarkan ulang.')
            ->line('Ini adalah **koreksi ke-'.$this->correctionCount.'** untuk periode ini.')
            ->line('**Detail Gaji (Setelah Koreksi):**')
            ->line('Gaji Pokok: Rp '.number_format($originalSalary, 0, ',', '.'))
            ->line('Potongan: Rp '.number_format($deductionAmount, 0, ',', '.'))
            ->line('Total Diterima: Rp '.number_format($finalSalary, 0, ',', '.'))
            ->line('Tanggal Pembayaran: '.$paymentDate)
            ->action('Lihat Payroll Saya', url('/admin/my-payroll/'.$this->payrollDetail->id))
            ->line('Mohon maaf atas ketidaknyamanan ini.')
            ->salutation('Salam, Tim Finance');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'payroll',
            'title' => 'Payroll Corrected',
            'body' => 'Your salary has been corrected and re-paid (correction #'.$this->correctionCount.'). Tap to view updated payslip.',
            'action_url' => '/admin/my-payroll/'.$this->payrollDetail->id,
            'payroll_detail_id' => $this->payrollDetail->id,
            'payroll_id' => $this->payrollDetail->payroll_id,
            'salary_month' => $this->payrollDetail->payroll->salary_month,
            'payment_date' => $this->payrollDetail->payroll->payment_date,
            'original_salary' => $this->payrollDetail->original_salary,
            'final_salary' => $this->payrollDetail->final_salary,
            'correction_count' => $this->correctionCount,
        ];
    }
}
