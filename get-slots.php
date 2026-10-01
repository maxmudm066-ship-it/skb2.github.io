<?php
/**
 * API: свободные слоты конкретного дня (шаг 3).
 * GET api/get-slots.php?doctor_id=N&date=YYYY-MM-DD
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Helpers::jsonOut(['ok' => false, 'message' => 'Method not allowed'], 405);
}

$doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
$date     = (string)($_GET['date'] ?? '');

if (!$doctorId || $doctorId < 1 || !ScheduleService::isValidDate($date)) {
    Helpers::jsonOut(['ok' => false, 'message' => 'Bad request'], 400);
}

$slots = ScheduleService::daySlots($doctorId, $date);

Helpers::jsonOut([
    'ok'    => true,
    'date'  => $date,
    'slots' => $slots,
]);
