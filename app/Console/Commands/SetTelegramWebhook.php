<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-webhook {url?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Установить webhook для Telegram бота';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegramService)
    {
        $url = $this->argument('url');

        if (!$url) {
            $url = $this->ask('Введите URL для webhook (например: https://yourdomain.com/api/telegram/webhook)');
        }

        $this->info("Устанавливаю webhook: {$url}");

        $result = $telegramService->setWebhook($url);

        if (isset($result['ok']) && $result['ok']) {
            $this->info('✅ Webhook успешно установлен!');
            $this->line('Описание: ' . ($result['description'] ?? 'N/A'));
        } else {
            $this->error('❌ Ошибка при установке webhook');
            $this->line('Ответ: ' . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}
