<?php
/**
 * Локализация интерфейса: RU / UZ / EN.
 * Словари в includes/lang/{ru,uz,en}.php.
 * Для контента из БД используется tx(): поле с суффиксом языка с fallback на _ru.
 */

declare(strict_types=1);

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Lang
{
    public const SUPPORTED = ['ru', 'uz', 'en'];

    private static string $lang = 'ru';
    private static array $dict = [];

    public static function init(): void
    {
        $lang = null;

        // 1) явный выбор через ?lang=
        $requested = $_GET['lang'] ?? null;
        if (is_string($requested) && in_array($requested, self::SUPPORTED, true)) {
            $lang = $requested;
            setcookie('ckb2_lang', $lang, [
                'expires'  => time() + 31536000,
                'path'     => (BASE_URL === '' ? '' : BASE_URL) . '/',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        // 2) сохранённый выбор (сессия или cookie)
        if ($lang === null) {
            $lang = $_SESSION['lang'] ?? null;
            if (!is_string($lang) || !in_array($lang, self::SUPPORTED, true)) {
                $lang = $_COOKIE['ckb2_lang'] ?? null;
                if (!is_string($lang) || !in_array($lang, self::SUPPORTED, true)) {
                    $lang = 'ru';
                }
            }
        }

        self::$lang = $lang;
        $_SESSION['lang'] = $lang;

        $file = APP_ROOT . '/includes/lang/' . $lang . '.php';
        self::$dict = is_file($file) ? (require $file) : [];
    }

    public static function current(): string
    {
        return self::$lang;
    }

    /**
     * Перевод по ключу. Подстановки: __('docs.office', 204) для «Кабинет {n}».
     * Второй аргумент подставляется вместо {m}.
     */
    public static function t(string $key, int|string|null ...$args): string
    {
        $text = self::$dict[$key] ?? $key;
        if ($args !== []) {
            $text = str_replace(
                ['{n}', '{m}'],
                [(string)($args[0] ?? ''), (string)($args[1] ?? '')],
                $text
            );
        }
        return $text;
    }

    /** Поле из строки БД с учётом языка: tx($row, 'name') → name_uz | name_ru */
    public static function tx(array $row, string $field): string
    {
        $localized = $row[$field . '_' . self::$lang] ?? null;
        if (is_string($localized) && trim($localized) !== '') {
            return $localized;
        }
        return (string)($row[$field . '_ru'] ?? '');
    }
}
