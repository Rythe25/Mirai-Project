<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TelegramClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.telegram.bot_token')) && filled(config('services.telegram.orders_chat_id'));
    }

    /** Sends an HTML message to the orders chat. Error messages never contain the bot token. */
    public function sendToOrdersChat(string $html): void
    {
        try {
            $response = Http::baseUrl('https://api.telegram.org')
                ->timeout(10)
                ->post('/bot'.config('services.telegram.bot_token').'/sendMessage', [
                    'chat_id' => config('services.telegram.orders_chat_id'),
                    'text' => $html,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);
        } catch (ConnectionException) {
            // The original message includes the URL, and the URL includes the token.
            throw new TelegramException('Telegram is unreachable.');
        }

        if (! $response->json('ok')) {
            throw new TelegramException('Telegram rejected the message: '.$response->json('description', 'HTTP '.$response->status()));
        }
    }
}
