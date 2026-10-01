<?php
/**
 * Единая точка входа для всех страниц: автозагрузка классов,
 * безопасная сессия, локализация, обработчик необработанных исключений.
 */

declare(strict_types=1);

define('APP_RUNNING', true);

require dirname(__DIR__) . '/config/config.php';
require dirname(__DIR__) . '/config/database.php';

/* Автозагрузчик классов: classes/ИмяКласса.php */
spl_autoload_register(static function (string $class): void {
    $file = APP_ROOT . '/classes/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/* Сессия с флагами HttpOnly, Secure (при HTTPS) и SameSite=Strict */
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (BASE_URL === '' ? '' : BASE_URL) . '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/* Локализация (RU / UZ / EN) */
Lang::init();

/* Глобальная функция перевода */
if (!function_exists('__')) {
    function __(string $key, int|string|null ...$args): string
    {
        return Lang::t($key, ...$args);
    }
}

/* Обработчик необработанных исключений: без раскрытия деталей наружу */
set_exception_handler(static function (Throwable $e): void {
    error_log('[CKB2] ' . $e->getMessage() . ' в ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        exit(1);
    }
    http_response_code(500);
    $isApi = str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/api/');
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Internal server error'], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<!doctype html><html lang="ru"><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Ошибка — ЦКБ № 2</title>'
            . '<style>body{font-family:system-ui,sans-serif;background:#F8FAFC;color:#0A2540;'
            . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}'
            . '.c{text-align:center;padding:40px}h1{font-size:22px}p{color:#64748B}</style>'
            . '<div class="c"><h1>Внутренняя ошибка сервера</h1>'
            . '<p>Мы уже работаем над устранением неполадки. Повторите попытку позже.</p></div>';
    }
    exit;
});
