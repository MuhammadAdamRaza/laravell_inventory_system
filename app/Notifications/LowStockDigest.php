<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    /**
     * @param  list<array{product_name: string, sku: string, warehouse_name: string, quantity: int, minimum_stock: int}>  $items
     */
    public function __construct(public array $items)
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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Daily low-stock inventory digest')
            ->greeting('Inventory attention required')
            ->line(count($this->items).' product and warehouse combinations are low or out of stock.');

        foreach ($this->items as $item) {
            $mail->line("{$item['product_name']} ({$item['sku']}) — {$item['warehouse_name']}: {$item['quantity']} available; minimum {$item['minimum_stock']}.");
        }

        return $mail
            ->action('Review stock', rtrim((string) config('app.url'), '/').'/inventory/balances')
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
            'kind' => 'low_stock_digest',
            'title' => 'Daily low-stock digest',
            'message' => count($this->items).' product and warehouse combinations need attention.',
            'item_count' => count($this->items),
            'url' => '/inventory/balances',
        ];
    }
}
