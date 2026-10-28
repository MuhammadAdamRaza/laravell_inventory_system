<?php

namespace App\Notifications;

use App\Models\SalesReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SalesReturnCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public SalesReturn $salesReturn)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Return {$this->salesReturn->return_number} completed")
            ->greeting('Sales return completed')
            ->line("Return {$this->salesReturn->return_number} for order {$this->salesReturn->salesOrder->order_number} has been completed.")
            ->line("Returned quantity: {$this->salesReturn->items->sum('quantity_returned')} unit(s).")
            ->action('View sales return', rtrim((string) config('app.url'), '/').'/sales-returns/'.$this->salesReturn->id)
            ->salutation((string) config('app.name'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'sales_return_completed',
            'title' => 'Sales return completed',
            'message' => 'Sales return '.$this->salesReturn->return_number.' has been completed.',
            'sales_return_id' => $this->salesReturn->id,
            'return_number' => $this->salesReturn->return_number,
            'sales_order_id' => $this->salesReturn->sales_order_id,
            'status' => $this->salesReturn->status,
            'url' => '/sales-returns/'.$this->salesReturn->id,
        ];
    }
}
