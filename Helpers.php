<?php
/**
 * Набор вспомогательных функций: экранирование, URL, JSON-ответы,
 * нормализация и форматирование узбекистанского номера телефона, даты, склонения.
 */

declare(strict_types=1);

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Helpers
{
    /** Экранирование для вывода в HTML (XSS-защита) */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /** Абсолютный путь от корня сайта */
    public static function url(string $path = ''): string
    {
        return BASE_URL . '/' . ltrim($path, '/');
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    /** Единый JSON-ответ для API */
    public static function jsonOut(array $payload, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Приводит любой ввод к нормализованному виду +998XXXXXXXXX
     * либо null, если номер не соответствует формату Узбекистана.
     */
    public static function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '998')) {
            $digits = substr($digits, 3);
        }
        if (strlen($digits) !== 9 || !ctype_digit($digits)) {
            return null;
        }
        return '+998' . $digits;
    }

    /** +998901234567 → +998 (90) 123-45-67 */
    public static function formatPhone(?string $phone): string
    {
        $phone = (string)$phone;
        if ($phone === '') {
            return '';
        }
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 12 && str_starts_with($digits, '998')) {
            return sprintf(
                '+998 (%s) %s-%s-%s',
                substr($digits, 3, 2),
                substr($digits, 5, 3),
                substr($digits, 8, 2),
                substr($digits, 10, 2)
            );
        }
        return $phone;
    }

    /** Локализованная дата: «5 октября 2026» / «5 oktabr 2026» / «5 October 2026» */
    public static function formatDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return '';
        }
        $month = Lang::t('month.' . (int)date('n', $ts));
        if (str_starts_with($month, 'month.')) {
            return date('d.m.Y', $ts); // локализация недоступна — fallback
        }
        return date('j', $ts) . ' ' . $month . ' ' . date('Y', $ts);
    }

    /**
     * Согласование числительных: plural(2, 'doctor') → «врача».
     * Поддерживаемые kind: doctor, year.
     */
    public static function plural(int $n, string $kind, ?string $lang = null): string
    {
        $lang ??= Lang::current();
        $n = abs($n);
        if ($lang === 'ru') {
            $forms = [
                'doctor' => ['врач', 'врача', 'врачей'],
                'year'   => ['год', 'года', 'лет'],
            ][$kind] ?? [''];
            if ($n % 100 >= 11 && $n % 100 <= 14) {
                return $forms[2];
            }
            return match (true) {
                $n % 10 === 1 => $forms[0],
                $n % 10 >= 2 && $n % 10 <= 4 => $forms[1],
                default => $forms[2],
            };
        }
        if ($lang === 'en') {
            return match ($kind) {
                'doctor' => $n === 1 ? 'doctor' : 'doctors',
                'year'   => $n === 1 ? 'year' : 'years',
                default => '',
            };
        }
        // uz — формы множественного числа не отличаются
        return match ($kind) {
            'doctor' => 'shifokor',
            'year'   => 'yil',
            default => '',
        };
    }

    /** Русская подпись статуса заявки (для админ-панели) */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'new'       => 'Новая',
            'confirmed' => 'Подтверждена',
            'completed' => 'Завершена',
            'cancelled' => 'Отменена',
            default     => $status,
        };
    }

    /** Инициалы для аватара без фото: «Каримова Дилноза» → «КД» */
    public static function initials(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $initials .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $initials !== '' ? $initials : '—';
    }

    /* ---- Flash-сообщения (одноразовые уведомления в сессии) ---- */

    public static function flashSet(string $key, string $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function flashGet(string $key): ?string
    {
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /** Текущий IP клиента */
    public static function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
