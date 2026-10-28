<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\UnreadNotificationCount;
use Illuminate\Notifications\Events\NotificationSent;

class InvalidateUnreadNotificationCount
{
    public function __construct(private UnreadNotificationCount $counts) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel === 'database' && $event->notifiable instanceof User) {
            $this->counts->forget($event->notifiable);
        }
    }
}
