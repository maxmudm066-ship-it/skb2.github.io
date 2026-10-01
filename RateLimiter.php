<?php
/**
 * Ограничение частоты запросов по IP-адресу (таблица rate_limits).
 * Пример: не более 3 заявок на приём за 10 минут с одного IP.
 */

declare(strict_types=1);

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Forbidden');
}

final class RateLimiter
{
    /**
     * Проверяет, превышен ли лимит действий для текущего IP.
     * Периодически очищает устаревшие записи.
     */
    public static function tooManyAttempts(string $action, int $max, int $windowSeconds): bool
    {
        $db = Database::get();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $since = date('Y-m-d H:i:s', time() - $windowSeconds);

        // фоновая очистка старых записей (1 из 50 запросов)
        if (random_int(1, 50) === 1) {
            $st = $db->prepare("DELETE FROM rate_limits WHERE created_at < ?");
            $st->execute([date('Y-m-d H:i:s', time() - 86400)]);
        }

        $st = $db->prepare(
            "SELECT COUNT(*) FROM rate_limits
             WHERE ip_address = ? AND action = ? AND created_at >= ?"
        );
        $st->execute([$ip, $action, $since]);

        return (int)$st->fetchColumn() >= $max;
    }

    /** Фиксирует выполненное действие. */
    public static function hit(string $action): void
    {
        $st = Database::get()->prepare(
            "INSERT INTO rate_limits (ip_address, action) VALUES (?, ?)"
        );
        $st->execute([$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $action]);
    }
}
