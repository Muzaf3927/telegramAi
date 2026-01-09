<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $token;
    private string $apiUrl;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}";
    }

    /**
     * Отправка сообщения пользователю
     */
    public function sendMessage(int $chatId, string $text, array $replyMarkup = null): array
    {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        try {
            $response = Http::post("{$this->apiUrl}/sendMessage", $data);
            
            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Telegram API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Telegram Service Exception', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Создание клавиатуры с кнопками
     */
    public function createKeyboard(array $buttons): array
    {
        return [
            'keyboard' => $buttons,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Установка webhook
     */
    public function setWebhook(string $url): array
    {
        try {
            $response = Http::post("{$this->apiUrl}/setWebhook", [
                'url' => $url,
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Set Webhook Exception', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
