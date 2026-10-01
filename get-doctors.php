<?php
/**
 * API: список врачей отделения для мастера записи (шаг 2).
 * GET api/get-doctors.php?department_id=N
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Helpers::jsonOut(['ok' => false, 'message' => 'Method not allowed'], 405);
}

$departmentId = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT);
if (!$departmentId || $departmentId < 1) {
    Helpers::jsonOut(['ok' => false, 'message' => 'Bad request'], 400);
}

$st = Database::get()->prepare(
    "SELECT id, name_ru, name_uz, name_en, position_ru, position_uz, position_en,
            category, experience_years, office, photo, slot_duration
     FROM doctors
     WHERE department_id = ? AND is_active = 1
     ORDER BY sort_order, id"
);
$st->execute([$departmentId]);

$doctors = [];
while ($row = $st->fetch()) {
    $name    = Lang::tx($row, 'name');
    $exp     = (int)$row['experience_years'];
    $office  = (string)$row['office'];
    $photo   = (string)$row['photo'];

    $doctors[] = [
        'id'         => (int)$row['id'],
        'name'       => $name,
        'position'   => Lang::tx($row, 'position'),
        'category'   => (string)$row['category'],
        'experience' => $exp > 0 ? __('docs.experience', $exp . ' ' . Helpers::plural($exp, 'year')) : '',
        'office'     => $office !== '' ? __('docs.office', $office) : '',
        'photo'      => ($photo !== '' && is_file(UPLOAD_DIR . '/' . $photo))
            ? Helpers::url('uploads/' . $photo)
            : null,
        'initials'   => Helpers::initials($name),
    ];
}

Helpers::jsonOut(['ok' => true, 'doctors' => $doctors]);
