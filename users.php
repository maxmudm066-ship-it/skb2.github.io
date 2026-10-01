<?php
/**
 * Управление пользователями админ-панели.
 * Защита: нельзя удалить или деактивировать собственную учётную запись.
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

Auth::requireAdmin();

$db = Database::get();

/* ---- POST-действия ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verifyRequest()) {
        Helpers::flashSet('error', 'Недействительный CSRF-токен. Повторите действие.');
        Helpers::redirect(Helpers::url('admin/users.php'));
    }

    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if ($id === Auth::id() && ($action === 'toggle' || $action === 'delete')) {
        Helpers::flashSet('error', 'Нельзя изменить или удалить собственную учётную запись.');
        Helpers::redirect(Helpers::url('admin/users.php'));
    }

    if ($id > 0 && $action === 'toggle') {
        $st = $db->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?");
        $st->execute([$id]);
        Helpers::flashSet('success', 'Статус пользователя изменён.');
    } elseif ($id > 0 && $action === 'delete') {
        $st = $db->prepare("DELETE FROM users WHERE id = ?");
        $st->execute([$id]);
        Helpers::flashSet('success', 'Пользователь удалён.');
    }
    Helpers::redirect(Helpers::url('admin/users.php'));
}

/* ---- Список ---- */
$users = [];
$st = $db->query(
    "SELECT id, username, full_name, email, role, is_active, last_login, created_at
     FROM users
     ORDER BY role, full_name
     LIMIT 500"
);
while ($r = $st->fetch()) {
    $users[] = $r;
}

$pageTitle = 'Пользователи';
$activeNav = 'users';
require APP_ROOT . '/admin/partials/header.php';
?>

<div class="page-head">
    <h2>Пользователи <span class="table__muted">(<?= count($users) ?>)</span></h2>
    <div class="page-head__spacer"></div>
    <div class="page-head__actions">
        <a class="btn btn--accent btn--sm" href="<?= Helpers::e(Helpers::url('admin/user-form.php')) ?>">
            <svg class="icon"><use href="#a-plus"/></svg>
            Добавить пользователя
        </a>
    </div>
</div>

<div class="card">
    <?php if ($users === []): ?>
        <div class="empty">Пользователи не созданы.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Логин</th>
                    <th>Роль</th>
                    <th>Последний вход</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <?php $self = (int)$u['id'] === Auth::id(); ?>
                    <tr>
                        <td>
                            <?= Helpers::e($u['full_name']) ?>
                            <?php if ($u['email']): ?>
                                <div class="table__muted"><?= Helpers::e($u['email']) ?></div>
                            <?php endif; ?>
                            <?php if ($self): ?>
                                <div class="table__muted">— это вы —</div>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= Helpers::e($u['username']) ?></td>
                        <td>
                            <span class="badge badge--<?= $u['role'] === 'admin' ? 'new' : 'confirmed' ?>">
                                <?= $u['role'] === 'admin' ? 'Администратор' : 'Оператор' ?>
                            </span>
                        </td>
                        <td class="table__muted">
                            <?= $u['last_login']
                                ? Helpers::e(date('d.m.Y H:i', strtotime((string)$u['last_login'])))
                                : 'ещё не входил' ?>
                        </td>
                        <td>
                            <span class="badge badge--<?= $u['is_active'] ? 'completed' : 'muted' ?>">
                                <?= $u['is_active'] ? 'Активен' : 'Отключён' ?>
                            </span>
                        </td>
                        <td>
                            <div class="table__actions">
                                <a class="btn btn--outline btn--sm" href="<?= Helpers::e(Helpers::url('admin/user-form.php?id=' . (int)$u['id'])) ?>">
                                    <svg class="icon"><use href="#a-edit"/></svg>
                                    Изменить
                                </a>
                                <?php if (!$self): ?>
                                    <form method="post" action="<?= Helpers::e(Helpers::url('admin/users.php')) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <button class="btn btn--ghost btn--sm" type="submit">
                                            <?= $u['is_active'] ? 'Отключить' : 'Включить' ?>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= Helpers::e(Helpers::url('admin/users.php')) ?>"
                                          data-confirm="Удалить пользователя «<?= Helpers::e($u['full_name']) ?>»? Отменить действие будет невозможно.">
                                        <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button class="btn btn--danger btn--sm" type="submit">
                                            <svg class="icon"><use href="#a-trash"/></svg>
                                            Удалить
                                        </button>
                                    </form>
                                <?php endif; ?>
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
