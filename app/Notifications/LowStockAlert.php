<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public string $productName,
        public string $sku,
        public string $warehouseName,
        public int $warehouseId,
        public int $productId,
        public int $quantity,
        public int $minimumStock,
        public string $alertState,
    ) {
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
        $outOfStock = $this->alertState === 'out_of_stock';
        $url = rtrim((string) config('app.url'), '/').'/inventory/balances?'.http_build_query([
            'warehouse_id' => $this->warehouseId,
            'product_id' => $this->productId,
        ]);

        return (new MailMessage)
            ->subject(($outOfStock ? 'Out of stock' : 'Low stock').': '.$this->productName)
            ->greeting($outOfStock ? 'Urgent: stock is out' : 'Low stock alert')
            ->line($this->productName.' ('.$this->sku.') at '.$this->warehouseName.' needs attention.')
            ->line('Current quantity: '.$this->quantity.' | Minimum stock: '.$this->minimumStock)
            ->action('View stock', $url)
            ->salutation((string) config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $outOfStock = $this->alertState === 'out_of_stock';

        return [
            'kind' => $outOfStock ? 'out_of_stock' : 'low_stock',
            'title' => $outOfStock ? 'Out of stock' : 'Low stock',
            'message' => "{$this->productName} ({$this->sku}) at {$this->warehouseName}: {$this->quantity} available, minimum {$this->minimumStock}.",
            'product_id' => $this->productId,
            'warehouse_id' => $this->warehouseId,
            'quantity' => $this->quantity,
            'minimum_stock' => $this->minimumStock,
            'url' => '/inventory/balances?'.http_build_query([
                'warehouse_id' => $this->warehouseId,
                'product_id' => $this->productId,
            ]),
        ];
    }
}
