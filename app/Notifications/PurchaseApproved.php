<?php

namespace App\Notifications;

use App\Models\Purchase;
use App\Models\User;
use App\Services\MoneyAmount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseApproved extends Notification implements ShouldQueue
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
        $purchase = $this->purchase->loadMissing('supplier');
        $totalCents = MoneyAmount::toCents($purchase->grand_total);

        return (new MailMessage)
            ->subject("Purchase {$purchase->purchase_number} approved")
            ->greeting("Hello {$notifiable->name},")
            ->line("Purchase {$purchase->purchase_number} for {$purchase->supplier->name} has been approved.")
            ->line('Grand total: PKR '.MoneyAmount::display($totalCents))
            ->action('View purchase', rtrim((string) config('app.url'), '/').'/purchases/'.$purchase->id)
            ->line('The approved purchase is ready for receiving.')
            ->salutation((string) config('app.name'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'kind' => 'purchase_approved',
            'title' => 'Purchase approved',
            'message' => 'Purchase '.$this->purchase->purchase_number.' is approved and ready for receiving.',
            'purchase_id' => $this->purchase->getKey(),
            'url' => '/purchases/'.$this->purchase->getKey(),
        ];
    }
}
