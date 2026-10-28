<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public string $reportName)
    {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your '.$this->reportName.' export is ready')
            ->greeting('Your CSV export has completed.')
            ->line('The report file is ready to download from your report exports page.')
            ->action('Open report exports', rtrim((string) config('app.url'), '/').'/reports/exports')
            ->salutation((string) config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'report_export_ready',
            'title' => 'Report export ready',
            'message' => 'Your '.$this->reportName.' CSV export is ready to download.',
            'url' => '/reports/exports',
        ];
    }
}
