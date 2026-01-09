<?php

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    private TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Обработка webhook от Telegram
     */
    public function webhook(Request $request)
    {
        try {
            $update = $request->all();

            Log::info('Telegram Webhook', ['update' => $update]);

            // Обработка сообщения
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            }

            // Обработка callback query (нажатие на кнопку)
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
            }

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('Webhook Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Обработка входящего сообщения
     */
    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $user = $message['from'] ?? [];

        // Обработка команды /start
        if ($text === '/start' || $text === '/start@UPutiAiBot') {
            $this->handleStartCommand($chatId, $user);
            return;
        }

        // Получаем пользователя для проверки состояния
        $telegramUser = TelegramUser::where('chat_id', $chatId)->first();

        // Обработка нажатий на кнопки клавиатуры
        if ($text === '🎨 Генерация изображений') {
            $this->handleImageGeneration($chatId);
            return;
        }

        if ($text === '🎬 Генерация видео') {
            $this->handleVideoGeneration($chatId);
            return;
        }

        if ($text === '💰 Баланс') {
            $this->handleBalance($chatId);
            return;
        }

        if ($text === '💳 Пополнить счет') {
            $this->handleDepositRequest($chatId);
            return;
        }

        // Обработка ввода суммы для пополнения
        if ($telegramUser && $telegramUser->pending_action === 'deposit') {
            $this->handleDepositAmount($chatId, $text, $telegramUser);
            return;
        }

        // Здесь будет обработка промптов для генерации
    }

    /**
     * Обработка команды /start
     */
    private function handleStartCommand(int $chatId, array $user): void
    {
        // Сохраняем или обновляем пользователя
        TelegramUser::updateOrCreate(
            ['chat_id' => $chatId],
            [
                'username' => $user['username'] ?? null,
                'first_name' => $user['first_name'] ?? null,
                'last_name' => $user['last_name'] ?? null,
                'is_active' => true,
                'balance' => 0, // Инициализируем баланс
                'pending_action' => null,
            ]
        );

        // Создаем клавиатуру с кнопками
        $keyboard = $this->telegramService->createKeyboard([
            [
                ['text' => '🎨 Генерация изображений'],
                ['text' => '🎬 Генерация видео'],
            ],
            [
                ['text' => '💰 Баланс'],
                ['text' => '💳 Пополнить счет'],
            ],
        ]);

        // Отправляем приветственное сообщение с кнопками
        $welcomeText = "👋 Добро пожаловать!\n\n"
            . "Я бот для генерации изображений и видео с помощью AI.\n\n"
            . "Выберите модель:";

        $this->telegramService->sendMessage($chatId, $welcomeText, $keyboard);
    }

    /**
     * Обработка выбора генерации изображений
     */
    private function handleImageGeneration(int $chatId): void
    {
        $text = "🎨 Вы выбрали генерацию изображений!\n\n"
            . "Напишите промпт для генерации изображения:";

        $this->telegramService->sendMessage($chatId, $text);
    }

    /**
     * Обработка выбора генерации видео
     */
    private function handleVideoGeneration(int $chatId): void
    {
        $text = "🎬 Вы выбрали генерацию видео!\n\n"
            . "Напишите промпт для генерации видео:";

        $this->telegramService->sendMessage($chatId, $text);
    }

    /**
     * Показать баланс пользователя
     */
    private function handleBalance(int $chatId): void
    {
        $user = TelegramUser::where('chat_id', $chatId)->first();
        
        if (!$user) {
            $this->telegramService->sendMessage($chatId, '❌ Пользователь не найден. Отправьте /start');
            return;
        }

        $balance = number_format($user->balance, 2, '.', ' ');
        $text = "💰 Ваш баланс: <b>{$balance}</b> ₽";

        $this->telegramService->sendMessage($chatId, $text);
    }

    /**
     * Запрос на пополнение счета
     */
    private function handleDepositRequest(int $chatId): void
    {
        $user = TelegramUser::where('chat_id', $chatId)->first();
        
        if (!$user) {
            $this->telegramService->sendMessage($chatId, '❌ Пользователь не найден. Отправьте /start');
            return;
        }

        // Устанавливаем состояние ожидания ввода суммы
        $user->update(['pending_action' => 'deposit']);

        $text = "💳 Пополнение счета\n\n"
            . "Введите сумму для пополнения (например: 100 или 500.50):";

        $this->telegramService->sendMessage($chatId, $text);
    }

    /**
     * Обработка введенной суммы для пополнения
     */
    private function handleDepositAmount(int $chatId, string $text, TelegramUser $user): void
    {
        // Проверяем, является ли текст числом
        $amount = filter_var($text, FILTER_VALIDATE_FLOAT);

        if ($amount === false || $amount <= 0) {
            $this->telegramService->sendMessage(
                $chatId,
                "❌ Неверная сумма. Пожалуйста, введите положительное число (например: 100 или 500.50):"
            );
            return;
        }

        // Пополняем баланс
        $user->increment('balance', $amount);
        $user->update(['pending_action' => null]);

        $newBalance = number_format($user->fresh()->balance, 2, '.', ' ');
        $amountFormatted = number_format($amount, 2, '.', ' ');

        $text = "✅ Счет успешно пополнен!\n\n"
            . "Пополнено: <b>{$amountFormatted}</b> ₽\n"
            . "Новый баланс: <b>{$newBalance}</b> ₽";

        $this->telegramService->sendMessage($chatId, $text);
    }

    /**
     * Обработка callback query (нажатие на inline кнопку)
     */
    private function handleCallbackQuery(array $callbackQuery): void
    {
        // Здесь будет обработка нажатий на inline кнопки
        // Пока оставляем пустым
    }
}

