<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class UnreadNotificationCount
{
    public function count(User $user): int
    {
        try {
            return (int) Cache::remember(
                $this->key($user),
                now()->addSeconds(30),
                fn (): int => $user->unreadNotifications()->count(),
            );
        } catch (Throwable $exception) {
            Log::warning('Unread notification count cache unavailable.', [
                'user_id' => $user->getKey(),
                'exception' => $exception::class,
            ]);

            return $user->unreadNotifications()->count();
        }
    }

    public function forget(User $user): void
    {
        try {
            Cache::forget($this->key($user));
        } catch (Throwable $exception) {
            Log::warning('Unread notification count cache invalidation failed.', [
                'user_id' => $user->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }

    private function key(User $user): string
    {
        return 'notifications:unread-count:'.$user->getKey();
    }
}
