<?php
/**
 * Вход в админ-панель. Защита: CSRF, ограничение частоты попыток
 * (RATE_LOGIN_MAX за RATE_LOGIN_WINDOW по IP), защита от timing-атак в Auth.
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

if (Auth::check()) {
    Helpers::redirect(Helpers::url('admin/index.php'));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verifyRequest()) {
        Helpers::flashSet('login_error', 'Сессия истекла. Повторите вход.');
        Helpers::redirect(Helpers::url('admin/login.php'));
    }

    if (RateLimiter::tooManyAttempts('login', RATE_LOGIN_MAX, RATE_LOGIN_WINDOW)) {
        Helpers::flashSet(
            'login_error',
            'Слишком много попыток входа. Повторите через ' . (int)(RATE_LOGIN_WINDOW / 60) . ' минут.'
        );
        Helpers::redirect(Helpers::url('admin/login.php'));
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username !== '' && $password !== '' && Auth::attempt($username, $password)) {
        Helpers::redirect(Helpers::url('admin/index.php'));
    }

    RateLimiter::hit('login');
    Helpers::flashSet('login_error', 'Неверное имя пользователя или пароль.');
    Helpers::redirect(Helpers::url('admin/login.php'));
}

$flashError = Helpers::flashGet('login_error');
$expired = isset($_GET['expired']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Вход в панель управления — ЦКБ № 2</title>
    <link rel="icon" type="image/svg+xml" href="<?= Helpers::e(Helpers::url('assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= Helpers::e(Helpers::url('assets/css/admin.css')) ?>">
</head>
<body class="admin-body">

<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
    <symbol id="a-cross" viewBox="0 0 24 24"><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/></symbol>
    <symbol id="a-alert" viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></symbol>
</svg>

<div class="login-page">
    <div class="login-card">
        <span class="login-logo"><svg class="icon"><use href="#a-cross"/></svg></span>

        <h1>Панель управления</h1>
        <p class="login-card__sub">Центральная клиническая больница № 2<br>Регистратура и администрация</p>

        <?php if ($flashError): ?>
            <div class="login-alert">
                <svg class="icon"><use href="#a-alert"/></svg>
                <?= Helpers::e($flashError) ?>
            </div>
        <?php elseif ($expired): ?>
            <div class="login-alert">
                <svg class="icon"><use href="#a-alert"/></svg>
                Сессия завершена из-за длительного бездействия. Войдите снова.
            </div>
        <?php endif; ?>

        <form method="post" action="<?= Helpers::e(Helpers::url('admin/login.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
            <div class="field" style="margin-bottom:14px">
                <label class="field__label" for="username">Имя пользователя</label>
                <input class="field__input" type="text" id="username" name="username"
                       autocomplete="username" required autofocus maxlength="50">
            </div>
            <div class="field" style="margin-bottom:18px">
                <label class="field__label" for="password">Пароль</label>
                <input class="field__input" type="password" id="password" name="password"
                       autocomplete="current-password" required>
            </div>
            <button class="btn btn--accent" type="submit" style="width:100%">
                Войти
            </button>
        </form>

        <p class="login-note">
            Доступ только для сотрудников регистратуры и администрации.
            Все действия фиксируются в журнале системы.
        </p>
    </div>
</div>

</body>
</html>
