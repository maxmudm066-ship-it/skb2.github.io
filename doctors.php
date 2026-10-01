<?php
/**
 * Управление врачами: список, включение/выключение, удаление, ссылка на форму.
 * Удаление врача с заявками блокируется внешним ключом (FK RESTRICT).
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

Auth::requireAdmin();

$db = Database::get();

/* ---- POST-действия ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verifyRequest()) {
        Helpers::flashSet('error', 'Недействительный CSRF-токен. Повторите действие.');
        Helpers::redirect(Helpers::url('admin/doctors.php'));
    }

    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if ($id > 0 && $action === 'toggle') {
        $st = $db->prepare("UPDATE doctors SET is_active = 1 - is_active WHERE id = ?");
        $st->execute([$id]);
        Helpers::flashSet('success', 'Статус врача изменён.');
    } elseif ($id > 0 && $action === 'delete') {
        try {
            $st = $db->prepare("SELECT photo FROM doctors WHERE id = ?");
            $st->execute([$id]);
            $photo = $st->fetchColumn();
            $st = $db->prepare("DELETE FROM doctors WHERE id = ?");
            $st->execute([$id]);
            if ($photo) {
                Upload::delete((string)$photo);
            }
            Helpers::flashSet('success', 'Врач удалён.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                Helpers::flashSet('error', 'Нельзя удалить врача: за ним числятся заявки на приём. Снимите врача с приёма (выключите активность) вместо удаления.');
            } else {
                throw $e;
            }
        }
    }
    Helpers::redirect(Helpers::url('admin/doctors.php'));
}

/* ---- Список ---- */
$doctors = [];
$st = $db->query(
    "SELECT d.id, d.name_ru, d.position_ru, d.category, d.office, d.photo,
            d.slot_duration, d.is_active, d.sort_order, dep.name_ru AS dept_name,
            (SELECT COUNT(*) FROM doctor_schedules s WHERE s.doctor_id = d.id) AS intervals_cnt
     FROM doctors d
     JOIN departments dep ON dep.id = d.department_id
     ORDER BY dep.sort_order, d.sort_order, d.name_ru"
);
while ($r = $st->fetch()) {
    $doctors[] = $r;
}

$pageTitle = 'Врачи';
$activeNav = 'doctors';
require APP_ROOT . '/admin/partials/header.php';
?>

<div class="page-head">
    <h2>Врачи <span class="table__muted">(<?= count($doctors) ?>)</span></h2>
    <div class="page-head__spacer"></div>
    <div class="page-head__actions">
        <a class="btn btn--accent btn--sm" href="<?= Helpers::e(Helpers::url('admin/doctor-form.php')) ?>">
            <svg class="icon"><use href="#a-plus"/></svg>
            Добавить врача
        </a>
    </div>
</div>

<div class="card">
    <?php if ($doctors === []): ?>
        <div class="empty">Врачи не добавлены.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th></th>
                    <th>Врач</th>
                    <th>Отделение</th>
                    <th>Приём</th>
                    <th>Активен</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($doctors as $d): ?>
                    <tr>
                        <td>
                            <?php if ($d['photo']): ?>
                                <img class="thumb" src="<?= Helpers::e(Helpers::url('uploads/' . $d['photo'])) ?>"
                                     alt="<?= Helpers::e($d['name_ru']) ?>">
                            <?php else: ?>
                                <span class="thumb-empty"><?= Helpers::e(Helpers::initials($d['name_ru'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= Helpers::e($d['name_ru']) ?>
                            <div class="table__muted"><?= Helpers::e($d['position_ru']) ?></div>
                            <?php if ($d['category']): ?>
                                <div class="table__muted"><?= Helpers::e($d['category']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="table__muted"><?= Helpers::e($d['dept_name']) ?></td>
                        <td class="num">
                            <?= (int)$d['slot_duration'] ?> мин/слот
                            <div class="table__muted">графиков: <?= (int)$d['intervals_cnt'] ?></div>
                        </td>
                        <td>
                            <span class="badge badge--<?= $d['is_active'] ? 'completed' : 'muted' ?>">
                                <?= $d['is_active'] ? 'Активен' : 'Скрыт' ?>
                            </span>
                        </td>
                        <td>
                            <div class="table__actions">
                                <a class="btn btn--outline btn--sm" href="<?= Helpers::e(Helpers::url('admin/doctor-form.php?id=' . (int)$d['id'])) ?>">
                                    <svg class="icon"><use href="#a-edit"/></svg>
                                    Изменить
                                </a>
                                <a class="btn btn--outline btn--sm" href="<?= Helpers::e(Helpers::url('admin/schedules.php?doctor=' . (int)$d['id'])) ?>">
                                    <svg class="icon"><use href="#a-clock"/></svg>
                                    Расписание
                                </a>
                                <form method="post" action="<?= Helpers::e(Helpers::url('admin/doctors.php')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button class="btn btn--ghost btn--sm" type="submit">
                                        <?= $d['is_active'] ? 'Скрыть' : 'Включить' ?>
                                    </button>
                                </form>
                                <form method="post" action="<?= Helpers::e(Helpers::url('admin/doctors.php')) ?>"
                                      data-confirm="Удалить врача «<?= Helpers::e($d['name_ru']) ?>»? Отменить действие будет невозможно.">
                                    <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="btn btn--danger btn--sm" type="submit">
                                        <svg class="icon"><use href="#a-trash"/></svg>
                                        Удалить
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require APP_ROOT . '/admin/partials/footer.php'; ?>
