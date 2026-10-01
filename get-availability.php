<?php
/**
 * API: доступность дней месяца для календаря мастера записи (шаг 3).
 * GET api/get-availability.php?doctor_id=N&month=YYYY-MM
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Helpers::jsonOut(['ok' => false, 'message' => 'Method not allowed'], 405);
}

$doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
$month    = (string)($_GET['month'] ?? '');

if (!$doctorId || $doctorId < 1 || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    Helpers::jsonOut(['ok' => false, 'message' => 'Bad request'], 400);
}

$availability = ScheduleService::monthAvailability($doctorId, $month);

Helpers::jsonOut([
    'ok'   => true,
    'month' => $month,
    'days' => $availability['days'],
]);
