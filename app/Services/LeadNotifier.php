<?php

namespace App\Services;

use App\Models\Lead;
use App\Support\Format;
use App\Support\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends new website requests to a Telegram chat, so that managers see
 * them on the phone at once. Enabled when TELEGRAM_BOT_TOKEN and
 * TELEGRAM_CHAT_ID are set in .env.
 */
class LeadNotifier
{
    public function send(Lead $lead): void
    {
        $token = config('services.telegram.bot_token');
        $chat = config('services.telegram.chat_id');
        if (! $token || ! $chat) {
            return;
        }

        $lines = [
            '🆕 Yangi ariza #'.$lead->id,
            '👤 '.$lead->name,
            '📞 '.Format::phone($lead->phone),
        ];
        if ($lead->service) {
            $lines[] = '📋 '.(Services::titles()[$lead->service] ?? $lead->service);
        }
        if ($lead->message) {
            $lines[] = '💬 '.$lead->message;
        }
        $lines[] = route('admin.leads.show', $lead);

        try {
            Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chat,
                'text' => implode("\n", $lines),
                'disable_web_page_preview' => true,
            ])->throw();
        } catch (Throwable $e) {
            Log::warning('Telegram notification failed: '.$e->getMessage());
        }
    }
}
