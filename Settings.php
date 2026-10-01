<?php
/**
 * Доступ к таблице настроек (ключ → значение) с кэшем на запрос.
 */

declare(strict_types=1);

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                $rows = Database::get()
                    ->query("SELECT setting_key, setting_value FROM settings")
                    ->fetchAll();
                foreach ($rows as $r) {
                    self::$cache[$r['setting_key']] = (string)$r['setting_value'];
                }
            } catch (Throwable $e) {
                error_log('[CKB2] settings: ' . $e->getMessage());
            }
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();
        return array_key_exists($key, $all) && $all[$key] !== ''
            ? $all[$key]
            : $default;
    }

    /** Настройка с учётом языка: сначала "ключ_lang", затем просто "ключ" */
    public static function getL(string $key, string $default = ''): string
    {
        $v = self::get($key . '_' . Lang::current());
        if ($v !== '') {
            return $v;
        }
        return self::get($key, $default);
    }

    public static function set(string $key, string $value): void
    {
        $st = Database::get()->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $st->execute([$key, $value]);
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }
}
