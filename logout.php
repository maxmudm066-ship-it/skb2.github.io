<?php
/**
 * Выход из админ-панели. Только POST с CSRF-токеном (защита от logout-CSRF).
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::verifyRequest()) {
    Auth::logout();
    Helpers::redirect(Helpers::url('admin/login.php'));
}

Helpers::redirect(Helpers::url('admin/index.php'));
