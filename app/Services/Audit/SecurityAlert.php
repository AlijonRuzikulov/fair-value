<?php

namespace App\Services\Audit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends important security and system events to the Telegram chat of the
 * company (TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID). The same alert is sent
 * at most once every 15 minutes.
 */
class SecurityAlert
{
    public static function send(string $key, string $text): void
    {
        if (! Cache::add('alert:'.$key, true, now()->addMinutes(15))) {
            return;
        }

        Log::warning('Security alert: '.$text);

        $token = config('services.telegram.bot_token');
        $chat = config('services.telegram.chat_id');
        if (! $token || ! $chat) {
            return;
        }

        try {
            Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chat,
                'text' => '⚠️ '.config('app.name').': '.$text,
                'disable_web_page_preview' => true,
            ]);
        } catch (Throwable) {
            // An alert must never break the request that raised it.
        }
    }
}
