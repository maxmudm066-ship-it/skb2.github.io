<?php
/**
 * API: создание заявки на приём (шаг 4 → 5).
 * POST api/book-appointment.php  (JSON-тело)
 *
 * Защита: CSRF-токен, honeypot-поле, ограничение частоты по IP,
 * полная серверная валидация данных и слота (Appointment::create).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    Helpers::jsonOut(['ok' => false, 'message' => 'Method not allowed'], 405);
}

header('Content-Type: application/json; charset=utf-8');

$raw  = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

/* ---- CSRF: JSON-поле или заголовок X-CSRF-Token ---- */
if (!Csrf::verifyRequest()) {
    Helpers::jsonOut(['ok' => false, 'errors' => ['form' => __('bk.err_csrf')]], 403);
}

/* ---- Honeypot: скрытое поле должен оставить пустым человек ---- */
if (trim((string)($data['website'] ?? '')) !== '') {
    Helpers::jsonOut(['ok' => false, 'errors' => ['form' => __('bk.err_honeypot')]], 400);
}

/* ---- Rate limiting: не более RATE_APPOINTMENT_MAX заявок за окно ---- */
if (RateLimiter::tooManyAttempts('appointment', RATE_APPOINTMENT_MAX, RATE_APPOINTMENT_WINDOW)) {
    Helpers::jsonOut(['ok' => false, 'errors' => ['form' => __('bk.err_rate')]], 429);
}

$result = Appointment::create($data);

if (!$result['ok']) {
    Helpers::jsonOut($result, 400);
}

RateLimiter::hit('appointment');

Helpers::jsonOut($result);
