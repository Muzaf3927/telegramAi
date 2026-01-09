<?php

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use App\Services\TelegramService;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    private TelegramService $telegramService;
    private TranslationService $translationService;

    public function __construct(TelegramService $telegramService, TranslationService $translationService)
    {
        $this->telegramService = $telegramService;
        $this->translationService = $translationService;
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

            // Обработка callback query (нажатие на inline кнопку)
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

        if (!$telegramUser) {
            $this->handleStartCommand($chatId, $user);
            return;
        }

        $language = $telegramUser->language ?? 'ru';

        // Обработка выбора языка
        if ($text === '🇷🇺 Русский' || $text === "🇺🇿 O'zbek") {
            $selectedLanguage = $text === '🇷🇺 Русский' ? 'ru' : 'uz';
            $telegramUser->update(['language' => $selectedLanguage]);
            $this->showMainMenu($chatId, $selectedLanguage);
            return;
        }

        // Обработка кнопки "Назад"
        if ($text === $this->translationService->get('back', $language)) {
            $this->showMainMenu($chatId, $language);
            return;
        }

        // Обработка основных кнопок
        if ($text === $this->translationService->get('generate_photo', $language)) {
            $this->handlePhotoGeneration($chatId, $language);
            return;
        }

        if ($text === $this->translationService->get('generate_video', $language)) {
            $this->handleVideoGeneration($chatId, $language);
            return;
        }

        if ($text === $this->translationService->get('generate_voice', $language)) {
            $this->handleVoiceGeneration($chatId, $language);
            return;
        }

        if ($text === $this->translationService->get('my_balance', $language)) {
            $this->handleBalance($chatId, $language);
            return;
        }

        if ($text === $this->translationService->get('deposit', $language)) {
            $this->handleDepositRequest($chatId, $language);
            return;
        }

        // Обработка ввода суммы для пополнения
        if ($telegramUser->pending_action === 'deposit') {
            $this->handleDepositAmount($chatId, $text, $telegramUser, $language);
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
        $telegramUser = TelegramUser::updateOrCreate(
            ['chat_id' => $chatId],
            [
                'username' => $user['username'] ?? null,
                'first_name' => $user['first_name'] ?? null,
                'last_name' => $user['last_name'] ?? null,
                'is_active' => true,
                'balance' => 0,
                'pending_action' => null,
                'language' => 'ru', // По умолчанию русский
            ]
        );

        // Если язык уже выбран, показываем главное меню
        if ($telegramUser->language) {
            $this->showMainMenu($chatId, $telegramUser->language);
            return;
        }

        // Иначе показываем выбор языка
        $this->showLanguageSelection($chatId);
    }

    /**
     * Показать выбор языка
     */
    private function showLanguageSelection(int $chatId): void
    {
        $keyboard = $this->telegramService->createKeyboard([
            [
                ['text' => '🇷🇺 Русский'],
                ['text' => "🇺🇿 O'zbek"],
            ],
        ]);

        $text = $this->translationService->get('welcome', 'ru') . "\n\n" . $this->translationService->get('select_language', 'ru');

        $this->telegramService->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Показать главное меню
     */
    private function showMainMenu(int $chatId, string $language): void
    {
        $keyboard = $this->telegramService->createKeyboard([
            [
                ['text' => $this->translationService->get('generate_photo', $language)],
                ['text' => $this->translationService->get('generate_video', $language)],
            ],
            [
                ['text' => $this->translationService->get('generate_voice', $language)],
            ],
            [
                ['text' => $this->translationService->get('my_balance', $language)],
            ],
        ]);

        $text = $this->translationService->get('welcome', $language) . "\n\n" . $this->translationService->get('main_menu', $language);

        $this->telegramService->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Обработка генерации фото
     */
    private function handlePhotoGeneration(int $chatId, string $language): void
    {
        $keyboard = $this->telegramService->createKeyboard([
            [
                ['text' => $this->translationService->get('back', $language)],
            ],
        ]);

        $text = $this->translationService->get('photo_prompt', $language);

        $this->telegramService->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Обработка генерации видео
     */
    private function handleVideoGeneration(int $chatId, string $language): void
    {
        $keyboard = $this->telegramService->createKeyboard([
            [
                ['text' => $this->translationService->get('back', $language)],
            ],
        ]);

        $text = $this->translationService->get('video_prompt', $language);

        $this->telegramService->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Обработка генерации голоса
     */
    private function handleVoiceGeneration(int $chatId, string $language): void
    {
        $keyboard = $this->telegramService->createKeyboard([
            [
                ['text' => $this->translationService->get('back', $language)],
            ],
        ]);

        $text = $this->translationService->get('voice_prompt', $language);

        $this->telegramService->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Показать баланс пользователя
     */
    private function handleBalance(int $chatId, string $language): void
    {
        $user = TelegramUser::where('chat_id', $chatId)->first();

        if (!$user) {
            $this->telegramService->sendMessage($chatId, $this->translationService->get('user_not_found', $language));
            return;
        }

        $balance = number_format($user->balance, 2, '.', ' ');
        $text = $this->translationService->get('balance_text', $language, ['balance' => $balance]);

        $keyboard = $this->telegramService->createKeyboard([
            [
                ['text' => $this->translationService->get('deposit', $language)],
            ],
            [
                ['text' => $this->translationService->get('back', $language)],
            ],
        ]);

        $this->telegramService->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Запрос на пополнение счета
     */
    private function handleDepositRequest(int $chatId, string $language): void
    {
        $user = TelegramUser::where('chat_id', $chatId)->first();

        if (!$user) {
            $this->telegramService->sendMessage($chatId, $this->translationService->get('user_not_found', $language));
            return;
        }

        // Устанавливаем состояние ожидания ввода суммы
        $user->update(['pending_action' => 'deposit']);

        $keyboard = $this->telegramService->createKeyboard([
            [
                ['text' => $this->translationService->get('back', $language)],
            ],
        ]);

        $text = $this->translationService->get('deposit_request', $language);

        $this->telegramService->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Обработка введенной суммы для пополнения
     */
    private function handleDepositAmount(int $chatId, string $text, TelegramUser $user, string $language): void
    {
        // Проверяем, является ли текст числом
        $amount = filter_var($text, FILTER_VALIDATE_FLOAT);

        if ($amount === false || $amount <= 0) {
            $keyboard = $this->telegramService->createKeyboard([
                [
                    ['text' => $this->translationService->get('back', $language)],
                ],
            ]);

            $this->telegramService->sendMessage(
                $chatId,
                $this->translationService->get('deposit_invalid', $language),
                $keyboard
            );
            return;
        }

        // Пополняем баланс
        $user->increment('balance', $amount);
        $user->update(['pending_action' => null]);

        $newBalance = number_format($user->fresh()->balance, 2, '.', ' ');
        $amountFormatted = number_format($amount, 2, '.', ' ');

        $text = $this->translationService->get('deposit_success', $language, [
            'amount' => $amountFormatted,
            'balance' => $newBalance,
        ]);

        $keyboard = $this->telegramService->createKeyboard([
            [
                ['text' => $this->translationService->get('back', $language)],
            ],
        ]);

        $this->telegramService->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Обработка callback query (нажатие на inline кнопку)
     */
    private function handleCallbackQuery(array $callbackQuery): void
    {
        // Здесь будет обработка нажатий на inline кнопки
    }
}
