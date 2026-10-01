<?php
/**
 * Расписание врачей: недельный график приёма (интервалы по дням недели),
 * блокировка дат (отпуск/праздники, персонально или на всю клинику)
 * и блокировка отдельных слотов.
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

Auth::requireLogin();

$db = Database::get();

$weekdayNames = [
    1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг',
    5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье',
];
$weekdayShort = [1 => 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

/* ---- Список врачей ---- */
$doctors = [];
$st = $db->query("SELECT id, name_ru, slot_duration FROM doctors ORDER BY is_active DESC, name_ru");
while ($r = $st->fetch()) {
    $doctors[] = $r;
}
if ($doctors === []) {
    Helpers::flashSet('error', 'Сначала добавьте хотя бы одного врача.');
    Helpers::redirect(Helpers::url('admin/doctors.php'));
}

$doctorId = (int)($_GET['doctor'] ?? 0);
$validIds = array_map(static fn($d) => (int)$d['id'], $doctors);
if ($doctorId <= 0 || !in_array($doctorId, $validIds, true)) {
    $doctorId = (int)$doctors[0]['id'];
}

$qsDoctor = 'doctor=' . $doctorId;
$backUrl = Helpers::url('admin/schedules.php') . '?' . $qsDoctor;

/* ---- POST-действия ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verifyRequest()) {
        Helpers::flashSet('error', 'Недействительный CSRF-токен. Повторите действие.');
        Helpers::redirect($backUrl);
    }
    $action = (string)($_POST['action'] ?? '');

    /* Сохранение недельного графика */
    if ($action === 'week') {
        $iv = $_POST['iv'] ?? [];
        $slotDuration = (int)($_POST['slot_duration'] ?? 0);

        $rows = [];
        $errors = [];
        if (!is_array($iv)) {
            $iv = [];
        }
        foreach ([1, 2, 3, 4, 5, 6, 7] as $wd) {
            $vals = $iv[$wd] ?? [];
            if (!is_array($vals)) {
                continue;
            }
            for ($i = 0; $i + 1 < count($vals); $i += 2) {
                $start = trim((string)$vals[$i]);
                $end = trim((string)$vals[$i + 1]);
                if ($start === '' && $end === '') {
                    continue; // пустая строка «добавить интервал»
                }
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)
                    || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)
                    || $start >= $end) {
                    $errors[] = $weekdayNames[$wd] . ': интервал должен иметь вид «09:00 – 13:00», начало раньше конца.';
                    continue;
                }
                $rows[] = [$wd, $start . ':00', $end . ':00'];
            }
        }

        /* интервалы одного дня не должны пересекаться */
        $byDay = [];
        foreach ($rows as [$wd, $s, $e]) {
            $byDay[$wd][] = [$s, $e];
        }
        foreach ($byDay as $wd => $list) {
            usort($list, static fn($a, $b) => $a[0] <=> $b[0]);
            for ($i = 1; $i < count($list); $i++) {
                if ($list[$i][0] < $list[$i - 1][1]) {
                    $errors[] = $weekdayNames[$wd] . ': интервалы приёма пересекаются.';
                }
            }
        }

        if (!in_array($slotDuration, [15, 30, 60], true)) {
            $errors[] = 'Длительность слота: 15, 30 или 60 минут.';
        }

        if ($errors === []) {
            try {
                $db->beginTransaction();
                $st = $db->prepare("DELETE FROM doctor_schedules WHERE doctor_id = ?");
                $st->execute([$doctorId]);
                if ($rows !== []) {
                    $st = $db->prepare(
                        "INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time)
                         VALUES (?,?,?,?)"
                    );
                    foreach ($rows as [$wd, $s, $e]) {
                        $st->execute([$doctorId, $wd, $s, $e]);
                    }
                }
                $st = $db->prepare("UPDATE doctors SET slot_duration = ? WHERE id = ?");
                $st->execute([$slotDuration, $doctorId]);
                $db->commit();
                Helpers::flashSet('success', 'Недельный график сохранён.');
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if ($e->getCode() === '23000') {
                    Helpers::flashSet('error', 'Найдены дублирующиеся интервалы. Проверьте дни недели.');
                } else {
                    throw $e;
                }
            }
        } else {
            Helpers::flashSet('error', implode(' ', $errors));
        }
        Helpers::redirect($backUrl);
    }

    /* Блокировка даты */
    if ($action === 'block_date_add') {
        $date = (string)($_POST['block_date'] ?? '');
        $reason = trim((string)($_POST['reason'] ?? ''));
        $scope = ($_POST['scope'] ?? '') === 'clinic' ? 'clinic' : 'doctor';

        if (!ScheduleService::isValidDate($date)) {
            Helpers::flashSet('error', 'Укажите корректную дату блокировки.');
        } elseif (mb_strlen($reason) > 255) {
            Helpers::flashSet('error', 'Причина — не более 255 символов.');
        } else {
            try {
                $st = $db->prepare(
                    "INSERT INTO date_blocks (doctor_id, block_date, reason) VALUES (?,?,?)"
                );
                $st->execute([$scope === 'clinic' ? null : $doctorId, $date, $reason !== '' ? $reason : null]);
                Helpers::flashSet('success', 'Дата заблокирована.');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    Helpers::flashSet('error', 'Эта дата уже заблокирована.');
                } else {
                    throw $e;
                }
            }
        }
        Helpers::redirect($backUrl);
    }

    if ($action === 'block_date_del') {
        $blockId = (int)($_POST['id'] ?? 0);
        if ($blockId > 0) {
            $st = $db->prepare("DELETE FROM date_blocks WHERE id = ?");
            $st->execute([$blockId]);
            Helpers::flashSet('success', 'Блокировка даты снята.');
        }
        Helpers::redirect($backUrl);
    }

    /* Блокировка отдельного слота */
    if ($action === 'slot_block_add') {
        $date = (string)($_POST['block_date'] ?? '');
        $time = (string)($_POST['block_time'] ?? '');
        $reason = trim((string)($_POST['reason'] ?? ''));

        if (!ScheduleService::isValidDate($date)) {
            Helpers::flashSet('error', 'Укажите корректную дату.');
        } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            Helpers::flashSet('error', 'Укажите корректное время слота.');
        } else {
            try {
                $st = $db->prepare(
                    "INSERT INTO blocked_slots (doctor_id, block_date, block_time, reason)
                     VALUES (?,?,?,?)"
                );
                $st->execute([$doctorId, $date, $time . ':00', $reason !== '' ? $reason : null]);
                Helpers::flashSet('success', 'Слот заблокирован.');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    Helpers::flashSet('error', 'Этот слот уже заблокирован.');
                } else {
                    throw $e;
                }
            }
        }
        Helpers::redirect($backUrl);
    }

    if ($action === 'slot_block_del') {
        $slotId = (int)($_POST['id'] ?? 0);
        if ($slotId > 0) {
            $st = $db->prepare("DELETE FROM blocked_slots WHERE id = ? AND doctor_id = ?");
            $st->execute([$slotId, $doctorId]);
            Helpers::flashSet('success', 'Блокировка слота снята.');
        }
        Helpers::redirect($backUrl);
    }

    Helpers::redirect($backUrl);
}

/* ---- Текущие данные ---- */
$doctor = null;
foreach ($doctors as $d) {
    if ((int)$d['id'] === $doctorId) {
        $doctor = $d;
        break;
    }
}

$schedule = [];
$st = $db->prepare(
    "SELECT weekday, TIME_FORMAT(start_time, '%H:%i') AS s, TIME_FORMAT(end_time, '%H:%i') AS e
     FROM doctor_schedules WHERE doctor_id = ? ORDER BY weekday, start_time"
);
$st->execute([$doctorId]);
while ($r = $st->fetch()) {
    $schedule[(int)$r['weekday']][] = [$r['s'], $r['e']];
}

$today = date('Y-m-d');

$dateBlocks = [];
$st = $db->prepare(
    "SELECT id, doctor_id, block_date, reason FROM date_blocks
     WHERE block_date >= ? AND (doctor_id = ? OR doctor_id IS NULL)
     ORDER BY block_date LIMIT 100"
);
$st->execute([$today, $doctorId]);
while ($r = $st->fetch()) {
    $dateBlocks[] = $r;
}

$blockedSlots = [];
$st = $db->prepare(
    "SELECT id, block_date, TIME_FORMAT(block_time, '%H:%i') AS t, reason
     FROM blocked_slots
     WHERE doctor_id = ? AND block_date >= ?
     ORDER BY block_date, block_time LIMIT 100"
);
$st->execute([$doctorId, $today]);
while ($r = $st->fetch()) {
    $blockedSlots[] = $r;
}

$pageTitle = 'Расписание врачей';
$activeNav = 'schedules';
require APP_ROOT . '/admin/partials/header.php';
?>

<div class="page-head">
    <h2>Расписание врачей</h2>
    <div class="page-head__spacer"></div>
    <div class="page-head__actions">
        <a class="btn btn--outline btn--sm" href="<?= Helpers::e(Helpers::url('admin/appointments.php')) ?>">
            <svg class="icon"><use href="#a-calendar"/></svg>
            К записям
        </a>
    </div>
</div>

<div class="card">
    <div class="card__body">
        <form class="filters" method="get" action="<?= Helpers::e(Helpers::url('admin/schedules.php')) ?>">
            <div class="field">
                <label class="field__label" for="sel-doctor">Врач</label>
                <select class="field__select" id="sel-doctor" name="doctor" data-autosubmit>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= $doctorId === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= Helpers::e($d['name_ru']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <span class="field__label">Текущий слот</span>
                <p class="field__hint" style="margin:8px 0 0"><?= (int)$doctor['slot_duration'] ?> минут</p>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card__head">
        <h3 class="card__title">Недельный график приёма — <?= Helpers::e($doctor['name_ru']) ?></h3>
    </div>
    <div class="card__body">
        <form method="post" action="<?= Helpers::e($backUrl) ?>">
            <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="week">

            <?php foreach ([1, 2, 3, 4, 5, 6, 7] as $wd): ?>
                <div class="sched-day">
                    <div class="sched-day__name">
                        <?= $weekdayNames[$wd] ?>
                        <small><?= isset($schedule[$wd]) ? count($schedule[$wd]) . ' интервал(а)' : 'выходной' ?></small>
                    </div>
                    <div class="sched-intervals" data-wd="<?= $wd ?>">
                        <?php foreach ($schedule[$wd] ?? [] as [$s, $e]): ?>
                            <div class="sched-interval">
                                <input type="time" name="iv[<?= $wd ?>][]" value="<?= Helpers::e($s) ?>">
                                <span class="sched-interval__sep">—</span>
                                <input type="time" name="iv[<?= $wd ?>][]" value="<?= Helpers::e($e) ?>">
                            </div>
                        <?php endforeach; ?>
                        <div class="sched-interval">
                            <input type="time" name="iv[<?= $wd ?>][]" value="">
                            <span class="sched-interval__sep">—</span>
                            <input type="time" name="iv[<?= $wd ?>][]" value="">
                        </div>
                        <button type="button" class="btn btn--ghost btn--sm" data-add-interval="<?= $wd ?>">
                            <svg class="icon"><use href="#a-plus"/></svg>
                            Добавить интервал
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="field" style="max-width:240px;margin-top:16px">
                <label class="field__label" for="f-slot2">Длительность слота</label>
                <select class="field__select" id="f-slot2" name="slot_duration">
                    <?php foreach ([15 => '15 минут', 30 => '30 минут', 60 => '60 минут'] as $min => $label): ?>
                        <option value="<?= $min ?>" <?= (int)$doctor['slot_duration'] === $min ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p class="field__hint" style="margin-top:10px">
                Пустые интервалы игнорируются. Чтобы сделать день выходным, очистите все его интервалы.
            </p>

            <button class="btn btn--accent" type="submit" style="margin-top:14px">Сохранить график</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card__head">
        <h3 class="card__title">Блокировка дат <small class="table__muted">(отпуск, больничный, праздники)</small></h3>
    </div>
    <div class="card__body">
        <form class="filters" method="post" action="<?= Helpers::e($backUrl) ?>" style="margin-bottom:14px">
            <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="block_date_add">
            <div class="field">
                <label class="field__label" for="bd-date">Дата</label>
                <input class="field__input" type="date" id="bd-date" name="block_date" required>
            </div>
            <div class="field">
                <label class="field__label" for="bd-scope">Действует на</label>
                <select class="field__select" id="bd-scope" name="scope">
                    <option value="doctor">Только <?= Helpers::e($doctor['name_ru']) ?></option>
                    <option value="clinic">Всю клинику (праздник)</option>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="bd-reason">Причина</label>
                <input class="field__input" type="text" id="bd-reason" name="reason" maxlength="255"
                       placeholder="Отпуск, праздник…">
            </div>
            <div class="filters__actions">
                <button class="btn btn--primary btn--sm" type="submit">
                    <svg class="icon"><use href="#a-ban"/></svg>
                    Заблокировать
                </button>
            </div>
        </form>

        <?php if ($dateBlocks === []): ?>
            <div class="empty">Активных блокировок дат нет.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Действует на</th>
                        <th>Причина</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dateBlocks as $b): ?>
                        <tr>
                            <td class="num"><?= Helpers::e(Helpers::formatDate($b['block_date'])) ?></td>
                            <td>
                                <?php if ($b['doctor_id'] === null): ?>
                                    <span class="badge badge--accent">Вся клиника</span>
                                <?php else: ?>
                                    <span class="badge badge--muted">Врач</span>
                                <?php endif; ?>
                            </td>
                            <td class="table__muted"><?= Helpers::e($b['reason'] ?? '') ?></td>
                            <td>
                                <form method="post" action="<?= Helpers::e($backUrl) ?>"
                                      data-confirm="Снять блокировку на <?= Helpers::e(Helpers::formatDate($b['block_date'])) ?>?">
                                    <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
                                    <input type="hidden" name="action" value="block_date_del">
                                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                    <button class="btn btn--danger btn--sm" type="submit">Снять</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card__head">
        <h3 class="card__title">Блокировка отдельных слотов <small class="table__muted">(обход, совещание, срочный приём)</small></h3>
    </div>
    <div class="card__body">
        <form class="filters" method="post" action="<?= Helpers::e($backUrl) ?>" style="margin-bottom:14px">
            <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="slot_block_add">
            <div class="field">
                <label class="field__label" for="bs-date">Дата</label>
                <input class="field__input" type="date" id="bs-date" name="block_date" required>
            </div>
            <div class="field">
                <label class="field__label" for="bs-time">Время слота</label>
                <input class="field__input" type="time" id="bs-time" name="block_time"
                       step="<?= (int)$doctor['slot_duration'] * 60 ?>" required>
            </div>
            <div class="field">
                <label class="field__label" for="bs-reason">Причина</label>
                <input class="field__input" type="text" id="bs-reason" name="reason" maxlength="255">
            </div>
            <div class="filters__actions">
                <button class="btn btn--primary btn--sm" type="submit">
                    <svg class="icon"><use href="#a-ban"/></svg>
                    Заблокировать слот
                </button>
            </div>
        </form>

        <?php if ($blockedSlots === []): ?>
            <div class="empty">Заблокированных слотов нет.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Время</th>
                        <th>Причина</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($blockedSlots as $b): ?>
                        <tr>
                            <td class="num">
                                <?= Helpers::e(Helpers::formatDate($b['block_date'])) ?>
                                <span class="table__muted">(<?= $weekdayShort[(int)date('N', strtotime((string)$b['block_date']))] ?>)</span>
                            </td>
                            <td class="num"><?= Helpers::e($b['t']) ?></td>
                            <td class="table__muted"><?= Helpers::e($b['reason'] ?? '') ?></td>
                            <td>
                                <form method="post" action="<?= Helpers::e($backUrl) ?>"
                                      data-confirm="Разблокировать слот <?= Helpers::e($b['t']) ?>?">
                                    <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
                                    <input type="hidden" name="action" value="slot_block_del">
                                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                    <button class="btn btn--danger btn--sm" type="submit">Снять</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require APP_ROOT . '/admin/partials/footer.php'; ?>
