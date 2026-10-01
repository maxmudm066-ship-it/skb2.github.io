<?php
/**
 * Аутентификация сотрудников (админ-панель).
 * Пароли проверяются password_verify; новые хэши создаются
 * PASSWORD_ARGON2ID (при поддержке сервером) либо PASSWORD_DEFAULT.
 */

declare(strict_types=1);

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Auth
{
    /** Попытка входа. true — успех. */
    public static function attempt(string $username, string $password): bool
    {
        $db = Database::get();
        $st = $db->prepare(
            "SELECT id, username, full_name, password_hash, role, is_active
             FROM users WHERE username = ? LIMIT 1"
        );
        $st->execute([$username]);
        $user = $st->fetch();

        // защита от timing-атак: hash-проверка выполняется всегда
        $hash = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';

        if (
            !$user
            || !$user['is_active']
            || !password_verify($password, $hash)
        ) {
            return false;
        }

        // прозрачная миграция на Argon2id при необходимости
        if (password_needs_rehash($hash, self::algo())) {
            $st = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $st->execute([password_hash($password, self::algo()), $user['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['last_activity'] = time();

        $st = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $st->execute([$user['id']]);

        return true;
    }

    public static function algo(): string|int|null
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function userName(): string
    {
        return (string)($_SESSION['user_name'] ?? '');
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    /** Автовыход после SESSION_IDLE_TIMEOUT секунд простоя */
    public static function enforceIdleTimeout(): void
    {
        $last = (int)($_SESSION['last_activity'] ?? 0);
        if ($last > 0 && time() - $last > SESSION_IDLE_TIMEOUT) {
            self::logout();
            Helpers::redirect(Helpers::url('admin/login.php?expired=1'));
        }
        $_SESSION['last_activity'] = time();
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Helpers::redirect(Helpers::url('admin/login.php'));
        }
        self::enforceIdleTimeout();
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            exit('Доступ запрещён: требуются права администратора.');
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Strict',
            ]);
        }
        session_destroy();
    }
}
