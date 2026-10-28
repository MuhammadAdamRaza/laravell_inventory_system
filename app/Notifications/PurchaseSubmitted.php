<?php

namespace App\Notifications;

use App\Models\Purchase;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public Purchase $purchase)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $purchase = $this->purchase->loadMissing(['supplier', 'warehouse', 'creator']);

        return (new MailMessage)
            ->subject("Purchase {$purchase->purchase_number} needs approval")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$purchase->creator->name} submitted purchase {$purchase->purchase_number} for {$purchase->supplier->name}.")
            ->line('Warehouse: '.$purchase->warehouse->name)
            ->action('Review purchase', rtrim((string) config('app.url'), '/').'/purchases/'.$purchase->id)
            ->salutation((string) config('app.name'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'purchase_submitted',
            'title' => 'Purchase needs approval',
            'message' => 'Purchase '.$this->purchase->purchase_number.' was submitted for approval.',
            'purchase_id' => $this->purchase->getKey(),
            'url' => '/purchases/'.$this->purchase->getKey(),
        ];
    }
}
