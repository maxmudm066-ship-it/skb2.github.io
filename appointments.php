<?php
/**
 * Управление записями на приём: фильтры (дата, врач, статус, поиск),
 * смена статуса (AJAX через admin/api/status.php), экспорт CSV, печать.
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

Auth::requireLogin();

$db = Database::get();

/* ---- Параметры фильтров ---- */
$fDate = (string)($_GET['date'] ?? '');
$fDoctor = (int)($_GET['doctor'] ?? 0);
$fStatus = (string)($_GET['status'] ?? '');
$fQuery = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

if ($fDate !== '' && !ScheduleService::isValidDate($fDate)) {
    $fDate = '';
}
if (!in_array($fStatus, ['new', 'confirmed', 'completed', 'cancelled'], true)) {
    $fStatus = '';
}

$where = [];
$params = [];
if ($fDate !== '') {
    $where[] = 'a.appointment_date = ?';
    $params[] = $fDate;
}
if ($fDoctor > 0) {
    $where[] = 'a.doctor_id = ?';
    $params[] = $fDoctor;
}
if ($fStatus !== '') {
    $where[] = 'a.status = ?';
    $params[] = $fStatus;
}
if ($fQuery !== '') {
    $where[] = '(a.patient_name LIKE ? OR a.patient_phone LIKE ? OR a.ticket_number LIKE ?)';
    $like = '%' . $fQuery . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

/* ---- Список врачей для фильтра ---- */
$doctors = [];
$st = $db->query("SELECT id, name_ru FROM doctors ORDER BY name_ru");
while ($r = $st->fetch()) {
    $doctors[] = $r;
}

/* ---- Экспорт в CSV (Excel) ---- */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $st = $db->prepare(
        "SELECT a.ticket_number, a.created_at, a.patient_name, a.patient_phone,
                a.patient_birth_date, a.patient_passport, a.patient_pinfl, a.patient_comment,
                a.appointment_date, a.appointment_time, a.status,
                d.name_ru AS doctor_name, dep.name_ru AS dept_name, d.office
         FROM appointments a
         JOIN doctors d ON d.id = a.doctor_id
         JOIN departments dep ON dep.id = a.department_id
         $whereSql
         ORDER BY a.appointment_date DESC, a.appointment_time DESC
         LIMIT 10000"
    );
    $st->execute($params);
    $rows = $st->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="appointments-' . date('Y-m-d-Hi') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    echo "\xEF\xBB\xBF"; // BOM для корректного открытия в Excel

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Номер заявки', 'Создана', 'Пациент', 'Телефон', 'Дата рождения',
        'Паспорт', 'ПИНФЛ', 'Врач', 'Отделение', 'Кабинет',
        'Дата приёма', 'Время приёма', 'Статус', 'Комментарий',
    ], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['ticket_number'],
            $r['created_at'],
            $r['patient_name'],
            Helpers::formatPhone($r['patient_phone']),
            $r['patient_birth_date'],
            $r['patient_passport'],
            $r['patient_pinfl'],
            $r['doctor_name'],
            $r['dept_name'],
            $r['office'],
            $r['appointment_date'],
            substr((string)$r['appointment_time'], 0, 5),
            Helpers::statusLabel($r['status']),
            $r['patient_comment'],
        ], ';');
    }
    fclose($out);
    exit;
}

/* ---- Подсчёт и выборка страницы ---- */
$st = $db->prepare(
    "SELECT COUNT(*) FROM appointments a $whereSql"
);
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$st = $db->prepare(
    "SELECT a.id, a.ticket_number, a.patient_name, a.patient_phone, a.patient_birth_date,
            a.patient_passport, a.patient_pinfl, a.patient_comment,
            a.appointment_date, a.appointment_time, a.status, a.created_at,
            d.name_ru AS doctor_name, dep.name_ru AS dept_name, d.office
     FROM appointments a
     JOIN doctors d ON d.id = a.doctor_id
     JOIN departments dep ON dep.id = a.department_id
     $whereSql
     ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.id DESC
     LIMIT $perPage OFFSET $offset"
);
$st->execute($params);
$rows = $st->fetchAll();

/* ---- Ссылка с текущими фильтрами ---- */
$qs = http_build_query(array_filter([
    'date' => $fDate,
    'doctor' => $fDoctor ?: null,
    'status' => $fStatus,
    'q' => $fQuery !== '' ? $fQuery : null,
], static fn($v) => $v !== null && $v !== ''));
$baseLink = Helpers::url('admin/appointments.php') . ($qs !== '' ? '?' . $qs : '');

$pageTitle = 'Записи на приём';
$activeNav = 'appointments';
require APP_ROOT . '/admin/partials/header.php';
?>

<div class="page-head">
    <h2>Записи на приём <span class="table__muted">(<?= $total ?>)</span></h2>
    <div class="page-head__spacer"></div>
    <div class="page-head__actions">
        <button type="button" class="btn btn--outline btn--sm" data-print-btn>
            <svg class="icon"><use href="#a-printer"/></svg>
            Печать
        </button>
        <a class="btn btn--outline btn--sm" href="<?= Helpers::e($baseLink . ($qs !== '' ? '&' : '?') . 'export=csv') ?>">
            <svg class="icon"><use href="#a-download"/></svg>
            Экспорт CSV
        </a>
    </div>
</div>

<div class="card">
    <div class="card__body">
        <form class="filters" method="get" action="<?= Helpers::e(Helpers::url('admin/appointments.php')) ?>">
            <div class="field">
                <label class="field__label" for="f-date">Дата приёма</label>
                <input class="field__input" type="date" id="f-date" name="date" value="<?= Helpers::e($fDate) ?>">
            </div>
            <div class="field">
                <label class="field__label" for="f-doctor">Врач</label>
                <select class="field__select" id="f-doctor" name="doctor">
                    <option value="0">Все врачи</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= $fDoctor === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= Helpers::e($d['name_ru']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="f-status">Статус</label>
                <select class="field__select" id="f-status" name="status">
                    <option value="">Все статусы</option>
                    <?php foreach (['new', 'confirmed', 'completed', 'cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>>
                            <?= Helpers::e(Helpers::statusLabel($s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="f-q">Поиск</label>
                <input class="field__input" type="search" id="f-q" name="q" maxlength="100"
                       placeholder="ФИО, телефон или номер заявки" value="<?= Helpers::e($fQuery) ?>">
            </div>
            <div class="filters__actions">
                <button class="btn btn--primary btn--sm" type="submit">
                    <svg class="icon"><use href="#a-search"/></svg>
                    Применить
                </button>
                <a class="btn btn--ghost btn--sm" href="<?= Helpers::e(Helpers::url('admin/appointments.php')) ?>">Сбросить</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <?php if ($rows === []): ?>
        <div class="empty">Записей по заданным условиям не найдено.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Заявка</th>
                    <th>Пациент</th>
                    <th>Врач</th>
                    <th>Дата и время</th>
                    <th>Статус</th>
                    <th>Создана</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="num">
                            <?= Helpers::e('#' . ($r['ticket_number'] ?? $r['id'])) ?>
                            <?php if ($r['patient_comment']): ?>
                                <div class="table__muted" title="<?= Helpers::e(mb_substr((string)$r['patient_comment'], 0, 200)) ?>">
                                    есть комментарий
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= Helpers::e($r['patient_name']) ?>
                            <div class="table__muted"><?= Helpers::e(Helpers::formatPhone($r['patient_phone'])) ?></div>
                            <?php if ($r['patient_birth_date']): ?>
                                <div class="table__muted">др. <?= Helpers::e(Helpers::formatDate($r['patient_birth_date'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= Helpers::e($r['doctor_name']) ?>
                            <div class="table__muted"><?= Helpers::e($r['dept_name']) ?><?= $r['office'] ? ', каб. ' . Helpers::e($r['office']) : '' ?></div>
                        </td>
                        <td class="num">
                            <?= Helpers::e(Helpers::formatDate($r['appointment_date'])) ?>
                            <div class="table__muted"><?= Helpers::e(substr((string)$r['appointment_time'], 0, 5)) ?></div>
                        </td>
                        <td>
                            <select class="status-select" data-id="<?= (int)$r['id'] ?>"
                                    aria-label="Статус заявки <?= (int)$r['id'] ?>">
                                <?php foreach (['new', 'confirmed', 'completed', 'cancelled'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>>
                                        <?= Helpers::e(Helpers::statusLabel($s)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="num table__muted"><?= Helpers::e(date('d.m.Y H:i', strtotime((string)$r['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= Helpers::e($baseLink . ($qs !== '' ? '&' : '?') . 'page=' . ($page - 1)) ?>">&larr;</a>
                <?php else: ?>
                    <span class="is-disabled">&larr;</span>
                <?php endif; ?>

                <?php
                $from = max(1, $page - 2);
                $to = min($pages, $from + 4);
                $from = max(1, $to - 4);
                for ($p = $from; $p <= $to; $p++): ?>
                    <?php if ($p === $page): ?>
                        <span class="is-active"><?= $p ?></span>
                    <?php else: ?>
                        <a href="<?= Helpers::e($baseLink . ($qs !== '' ? '&' : '?') . 'page=' . $p) ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $pages): ?>
                    <a href="<?= Helpers::e($baseLink . ($qs !== '' ? '&' : '?') . 'page=' . ($page + 1)) ?>">&rarr;</a>
                <?php else: ?>
                    <span class="is-disabled">&rarr;</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require APP_ROOT . '/admin/partials/footer.php'; ?>
