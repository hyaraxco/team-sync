<?php

namespace App\Notifications;

use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayrollApproved extends Notification implements ShouldQueue
{
    use Queueable;

    protected Payroll $payroll;
    protected ?string $actorName;

    /**
     * Create a new notification instance.
     */
    public function __construct(Payroll $payroll, ?string $actorName = null)
    {
        $this->payroll = $payroll;
        $this->actorName = $actorName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $monthStr = Carbon::parse($this->payroll->salary_month)->format('F Y');

        $message = (new MailMessage)
            ->subject('Payroll ' . $monthStr . ' Telah Disetujui')
            ->greeting('Halo ' . $notifiable->name . ',')
            ->line('Draft payroll untuk periode **' . $monthStr . '** telah disetujui oleh tim Finance dan siap untuk dibayarkan.');

        if ($this->actorName) {
            $message->line('Disetujui oleh: ' . $this->actorName);
        }

        return $message
            ->action('Lihat Payroll', url('/admin/payroll/'.$this->payroll->id))
            ->line('Buka menu payroll untuk meninjau status terbarunya.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'payroll',
            'title' => 'Payroll Approved',
            'body' => 'The payroll draft for ' . Carbon::parse($this->payroll->salary_month)->format('F Y') . ' has been approved by Finance.',
            'action_url' => '/admin/payroll/'.$this->payroll->id,
            'payroll_id' => $this->payroll->id,
            'salary_month' => $this->payroll->salary_month,
            'actor_name' => $this->actorName,
        ];
    }
}
