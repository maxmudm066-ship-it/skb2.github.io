<?php
/**
 * Дашборд: статистика за сегодня и неделю, график записей за 7 дней,
 * ближайшие приёмы.
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

Auth::requireLogin();

$db = Database::get();
$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-6 days'));

$statToday = $statNew = $statConfirmed = $statWeek = 0;

$st = $db->prepare(
    "SELECT
        SUM(CASE WHEN appointment_date = ? AND status <> 'cancelled' THEN 1 ELSE 0 END) AS t,
        SUM(CASE WHEN appointment_date = ? AND status = 'new' THEN 1 ELSE 0 END) AS n,
        SUM(CASE WHEN appointment_date = ? AND status = 'confirmed' THEN 1 ELSE 0 END) AS c,
        SUM(CASE WHEN appointment_date BETWEEN ? AND ? AND status <> 'cancelled' THEN 1 ELSE 0 END) AS w
     FROM appointments"
);
$st->execute([$today, $today, $today, $weekAgo, $today]);
if ($row = $st->fetch()) {
    $statToday = (int)($row['t'] ?? 0);
    $statNew = (int)($row['n'] ?? 0);
    $statConfirmed = (int)($row['c'] ?? 0);
    $statWeek = (int)($row['w'] ?? 0);
}

/* График: записи по дням за последние 7 дней */
$chartData = [];
$st = $db->prepare(
    "SELECT appointment_date, COUNT(*) AS cnt
     FROM appointments
     WHERE appointment_date BETWEEN ? AND ? AND status <> 'cancelled'
     GROUP BY appointment_date"
);
$st->execute([$weekAgo, $today]);
$byDate = [];
while ($r = $st->fetch()) {
    $byDate[$r['appointment_date']] = (int)$r['cnt'];
}
$weekdaysShort = [1 => 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
$maxCnt = 0;
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $cnt = $byDate[$d] ?? 0;
    $maxCnt = max($maxCnt, $cnt);
    $chartData[] = [
        'date'  => $d,
        'cnt'   => $cnt,
        'label' => $weekdaysShort[(int)date('N', strtotime($d))] . ', ' . date('d.m', strtotime($d)),
        'today' => $i === 0,
    ];
}

/* Ближайшие активные приёмы (с текущего момента вперёд) */
$upcoming = [];
$st = $db->prepare(
    "SELECT a.id, a.ticket_number, a.appointment_date, a.appointment_time, a.status,
            a.patient_name, a.patient_phone,
            d.name_ru AS doctor_name, dep.name_ru AS dept_name, d.office
     FROM appointments a
     JOIN doctors d ON d.id = a.doctor_id
     JOIN departments dep ON dep.id = a.department_id
     WHERE a.status IN ('new','confirmed')
       AND (a.appointment_date > ? OR (a.appointment_date = ? AND a.appointment_time >= CURTIME()))
     ORDER BY a.appointment_date, a.appointment_time
     LIMIT 8"
);
$st->execute([$today, $today]);
while ($r = $st->fetch()) {
    $upcoming[] = $r;
}

$pageTitle = 'Дашборд';
$activeNav = 'dashboard';
require APP_ROOT . '/admin/partials/header.php';
?>

<div class="page-head">
    <h2>Обзор регистратуры</h2>
    <div class="page-head__spacer"></div>
    <div class="page-head__actions">
        <a class="btn btn--accent btn--sm" href="<?= Helpers::e(Helpers::url('admin/appointments.php')) ?>">
            <svg class="icon"><use href="#a-calendar"/></svg>
            Все записи
        </a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat">
        <span class="stat__icon"><svg class="icon"><use href="#a-calendar"/></svg></span>
        <div>
            <div class="stat__num"><?= $statToday ?></div>
            <div class="stat__label">приёмов сегодня</div>
        </div>
    </div>
    <div class="stat">
        <span class="stat__icon"><svg class="icon"><use href="#a-alert"/></svg></span>
        <div>
            <div class="stat__num"><?= $statNew ?></div>
            <div class="stat__label">новых заявок сегодня</div>
        </div>
    </div>
    <div class="stat stat--warn">
        <span class="stat__icon"><svg class="icon"><use href="#a-check"/></svg></span>
        <div>
            <div class="stat__num"><?= $statConfirmed ?></div>
            <div class="stat__label">подтверждённых сегодня</div>
        </div>
    </div>
    <div class="stat">
        <span class="stat__icon"><svg class="icon"><use href="#a-grid"/></svg></span>
        <div>
            <div class="stat__num"><?= $statWeek ?></div>
            <div class="stat__label">приёмов за 7 дней</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card__head">
        <h3 class="card__title">Записи за последние 7 дней</h3>
    </div>
    <div class="card__body">
        <div class="chart">
            <div class="chart__bars">
                <?php foreach ($chartData as $col): ?>
                    <?php
                    $h = $col['cnt'] > 0 ? max(4, (int)round($col['cnt'] / max(1, $maxCnt) * 100)) : 3;
                    ?>
                    <div class="chart__col">
                        <span class="chart__num"><?= $col['cnt'] ?></span>
                        <div class="chart__bar<?= $col['cnt'] === 0 ? ' is-empty' : '' ?>"
                             style="height:<?= $h ?>%"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="chart__bars" style="height:auto">
                <?php foreach ($chartData as $col): ?>
                    <div class="chart__col">
                        <span class="chart__label<?= $col['today'] ? ' is-today' : '' ?>"><?= Helpers::e($col['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card__head">
        <h3 class="card__title">Ближайшие приёмы</h3>
    </div>
    <?php if ($upcoming === []): ?>
        <div class="empty">Активных предстоящих записей нет.</div>
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
                </tr>
                </thead>
                <tbody>
                <?php foreach ($upcoming as $a): ?>
                    <tr>
                        <td class="num"><?= Helpers::e('#' . ($a['ticket_number'] ?? '')) ?></td>
                        <td>
                            <?= Helpers::e($a['patient_name']) ?>
                            <div class="table__muted"><?= Helpers::e(Helpers::formatPhone($a['patient_phone'])) ?></div>
                        </td>
                        <td>
                            <?= Helpers::e($a['doctor_name']) ?>
                            <div class="table__muted"><?= Helpers::e($a['dept_name']) ?><?= $a['office'] ? ', каб. ' . Helpers::e($a['office']) : '' ?></div>
                        </td>
                        <td class="num">
                            <?= Helpers::e(Helpers::formatDate($a['appointment_date'])) ?>
                            <div class="table__muted"><?= Helpers::e(substr((string)$a['appointment_time'], 0, 5)) ?></div>
                        </td>
                        <td><span class="badge badge--<?= Helpers::e($a['status']) ?>"><?= Helpers::e(Helpers::statusLabel($a['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require APP_ROOT . '/admin/partials/footer.php'; ?>
