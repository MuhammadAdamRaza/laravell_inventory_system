<?php

namespace App\Notifications;

use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SalesOrderConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    /**
     * Create a new notification instance.
     */
    public function __construct(public SalesOrder $salesOrder)
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
            ->subject('Sales order '.$this->salesOrder->order_number.' confirmed')
            ->greeting('Your sales order has been confirmed.')
            ->line('Order: '.$this->salesOrder->order_number)
            ->line('Customer: '.$this->salesOrder->customer->name)
            ->line('Total: PKR '.$this->salesOrder->grand_total)
            ->action('View sales order', rtrim((string) config('app.url'), '/').'/sales-orders/'.$this->salesOrder->id)
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
            'kind' => 'sales_order_confirmed',
            'title' => 'Sales order confirmed',
            'message' => 'Sales order '.$this->salesOrder->order_number.' has been confirmed.',
            'sales_order_id' => $this->salesOrder->id,
            'order_number' => $this->salesOrder->order_number,
            'status' => $this->salesOrder->status,
            'url' => '/sales-orders/'.$this->salesOrder->id,
        ];
    }
}
