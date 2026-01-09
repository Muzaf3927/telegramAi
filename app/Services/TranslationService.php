<?php

namespace App\Services;

class TranslationService
{
    private const TRANSLATIONS = [
        'ru' => [
            'welcome' => "👋 Добро пожаловать!\n\nЯ бот для генерации изображений, видео и голоса с помощью AI.",
            'select_language' => 'Выберите язык:',
            'main_menu' => 'Главное меню',
            'generate_photo' => '🎨 Генерация фото',
            'generate_video' => '🎬 Генерация видео',
            'generate_voice' => '🎤 Генерация голоса',
            'my_balance' => '💰 Мой баланс',
            'back' => '⬅️ Назад',
            'balance_text' => "💰 Ваш баланс: <b>{balance}</b> ₽",
            'deposit' => '💳 Пополнить счет',
            'deposit_request' => "💳 Пополнение счета\n\nВведите сумму для пополнения (например: 100 или 500.50):",
            'deposit_success' => "✅ Счет успешно пополнен!\n\nПополнено: <b>{amount}</b> ₽\nНовый баланс: <b>{balance}</b> ₽",
            'deposit_invalid' => "❌ Неверная сумма. Пожалуйста, введите положительное число (например: 100 или 500.50):",
            'photo_prompt' => "🎨 Вы выбрали генерацию фото!\n\nНапишите промпт для генерации фото:",
            'video_prompt' => "🎬 Вы выбрали генерацию видео!\n\nНапишите промпт для генерации видео:",
            'voice_prompt' => "🎤 Вы выбрали генерацию голоса!\n\nНапишите текст для генерации голоса:",
            'user_not_found' => '❌ Пользователь не найден. Отправьте /start',
        ],
        'uz' => [
            'welcome' => "👋 Xush kelibsiz!\n\nMen AI yordamida rasm, video va ovoz yaratish botiman.",
            'select_language' => 'Tilni tanlang:',
            'main_menu' => 'Asosiy menyu',
            'generate_photo' => '🎨 Rasm yaratish',
            'generate_video' => '🎬 Video yaratish',
            'generate_voice' => '🎤 Ovoz yaratish',
            'my_balance' => '💰 Mening balansim',
            'back' => '⬅️ Orqaga',
            'balance_text' => "💰 Sizning balansingiz: <b>{balance}</b> ₽",
            'deposit' => '💳 Hisobni to\'ldirish',
            'deposit_request' => "💳 Hisobni to\'ldirish\n\nTo\'ldirish uchun summani kiriting (masalan: 100 yoki 500.50):",
            'deposit_success' => "✅ Hisob muvaffaqiyatli to\'ldirildi!\n\nTo\'ldirildi: <b>{amount}</b> ₽\nYangi balans: <b>{balance}</b> ₽",
            'deposit_invalid' => "❌ Noto\'g\'ri summa. Iltimos, musbat son kiriting (masalan: 100 yoki 500.50):",
            'photo_prompt' => "🎨 Siz rasm yaratishni tanladingiz!\n\nRasm yaratish uchun prompt yozing:",
            'video_prompt' => "🎬 Siz video yaratishni tanladingiz!\n\nVideo yaratish uchun prompt yozing:",
            'voice_prompt' => "🎤 Siz ovoz yaratishni tanladingiz!\n\nOvoz yaratish uchun matn yozing:",
            'user_not_found' => '❌ Foydalanuvchi topilmadi. /start yuboring',
        ],
    ];

    public function get(string $key, string $language = 'ru', array $replace = []): string
    {
        $text = self::TRANSLATIONS[$language][$key] ?? self::TRANSLATIONS['ru'][$key] ?? $key;

        foreach ($replace as $search => $value) {
            $text = str_replace('{' . $search . '}', $value, $text);
        }

        return $text;
    }

    public function getLanguageName(string $code): string
    {
        return match($code) {
            'ru' => '🇷🇺 Русский',
            'uz' => '🇺🇿 O\'zbek',
            default => 'O\'zbek',
        };
    }
}
