<?php
/**
 * Учётная запись: создание и редактирование.
 * Пароль задаётся через password_hash(Auth::algo()) — Argon2id либо bcrypt.
 * Собственную роль и активность изменять нельзя.
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

Auth::requireAdmin();

$db = Database::get();
$id = (int)($_GET['id'] ?? 0);

/* ---- Загрузка текущих данных ---- */
$user = [
    'id' => 0, 'username' => '', 'full_name' => '', 'email' => '',
    'role' => 'operator', 'is_active' => 1,
];
if ($id > 0) {
    $st = $db->prepare("SELECT id, username, full_name, email, role, is_active FROM users WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        Helpers::flashSet('error', 'Пользователь не найден.');
        Helpers::redirect(Helpers::url('admin/users.php'));
    }
    $user = array_merge($user, $row);
}

/* ---- Сохранение ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verifyRequest()) {
        Helpers::flashSet('error', 'Недействительный CSRF-токен. Повторите действие.');
        Helpers::redirect(Helpers::url($id > 0 ? 'admin/user-form.php?id=' . $id : 'admin/user-form.php'));
    }

    $in = $_POST;
    $errors = [];

    $username = trim((string)($in['username'] ?? ''));
    $fullName = trim((string)($in['full_name'] ?? ''));
    $email = trim((string)($in['email'] ?? ''));
    $role = (string)($in['role'] ?? 'operator');
    $password = (string)($in['password'] ?? '');
    $passwordConfirm = (string)($in['password_confirm'] ?? '');

    $self = $id > 0 && $id === Auth::id();
    $isActive = $self ? 1 : (isset($in['is_active']) ? 1 : 0);
    if ($self) {
        $role = $user['role']; // собственную роль менять нельзя
    }

    if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
        $errors[] = 'Логин — от 3 до 50 символов: латиница, цифры, точки, дефисы.';
    }
    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 150) {
        $errors[] = 'ФИО — обязательно, от 2 до 150 символов.';
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100)) {
        $errors[] = 'Некорректный адрес электронной почты.';
    }
    if (!in_array($role, ['admin', 'operator'], true)) {
        $errors[] = 'Роль: администратор или оператор.';
    }

    /* пароль: обязателен при создании, опционален при редактировании */
    if ($id === 0) {
        if ($password === '') {
            $errors[] = 'Задайте пароль для нового пользователя.';
        }
    }
    if ($password !== '') {
        if (mb_strlen($password) < 8) {
            $errors[] = 'Пароль — не менее 8 символов.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors[] = 'Пароль должен содержать буквы и цифры.';
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'Пароли не совпадают.';
        }
    }

    if ($errors === []) {
        try {
            if ($id > 0) {
                if ($password !== '') {
                    $st = $db->prepare(
                        "UPDATE users SET username=?, full_name=?, email=?, role=?, is_active=?, password_hash=?
                         WHERE id=?"
                    );
                    $st->execute([
                        $username, $fullName, $email !== '' ? $email : null,
                        $role, $isActive, password_hash($password, Auth::algo()), $id,
                    ]);
                } else {
                    $st = $db->prepare(
                        "UPDATE users SET username=?, full_name=?, email=?, role=?, is_active=?
                         WHERE id=?"
                    );
                    $st->execute([
                        $username, $fullName, $email !== '' ? $email : null,
                        $role, $isActive, $id,
                    ]);
                }
            } else {
                $st = $db->prepare(
                    "INSERT INTO users (username, full_name, email, role, is_active, password_hash)
                     VALUES (?,?,?,?,?,?)"
                );
                $st->execute([
                    $username, $fullName, $email !== '' ? $email : null,
                    $role, $isActive, password_hash($password, Auth::algo()),
                ]);
                $id = (int)$db->lastInsertId();
            }

            Helpers::flashSet('success', 'Пользователь сохранён.');
            Helpers::redirect(Helpers::url('admin/users.php'));
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'Пользователь с таким логином уже существует.';
            } else {
                throw $e;
            }
        }
    }

    $user = array_merge($user, [
        'username' => $username, 'full_name' => $fullName, 'email' => $email,
        'role' => $role, 'is_active' => $isActive,
    ]);
    $formErrors = $errors;
}

$pageTitle = $id > 0 ? 'Пользователь: ' . $user['full_name'] : 'Новый пользователь';
$activeNav = 'users';
require APP_ROOT . '/admin/partials/header.php';
?>

<div class="page-head">
    <h2><?= $id > 0 ? 'Редактирование пользователя' : 'Новый пользователь' ?></h2>
    <div class="page-head__spacer"></div>
    <div class="page-head__actions">
        <a class="btn btn--ghost btn--sm" href="<?= Helpers::e(Helpers::url('admin/users.php')) ?>">
            &larr; К списку пользователей
        </a>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="flash flash--error" style="margin:0 0 16px">
        <svg class="icon"><use href="#a-alert"/></svg>
        <div>
            <?php foreach ($formErrors as $err): ?>
                <div><?= Helpers::e($err) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card__body">
        <form method="post" action="<?= Helpers::e(Helpers::url('admin/user-form.php' . ($id > 0 ? '?id=' . $id : ''))) ?>">
            <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">

            <div class="form-grid">
                <div class="field">
                    <label class="field__label" for="f-username">Логин <span class="req">*</span></label>
                    <input class="field__input" type="text" id="f-username" name="username" maxlength="50"
                           required autocomplete="off" value="<?= Helpers::e($user['username']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-fullname">ФИО <span class="req">*</span></label>
                    <input class="field__input" type="text" id="f-fullname" name="full_name" maxlength="150"
                           required value="<?= Helpers::e($user['full_name']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-email">Электронная почта</label>
                    <input class="field__input" type="email" id="f-email" name="email" maxlength="100"
                           autocomplete="off" value="<?= Helpers::e($user['email']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-role">Роль</label>
                    <select class="field__select" id="f-role" name="role" <?= $id > 0 && $id === Auth::id() ? 'disabled' : '' ?>>
                        <option value="operator" <?= $user['role'] === 'operator' ? 'selected' : '' ?>>
                            Оператор регистратуры
                        </option>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>
                            Администратор
                        </option>
                    </select>
                    <?php if ($id > 0 && $id === Auth::id()): ?>
                        <p class="field__hint">Собственную роль изменить нельзя.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-grid" style="margin-top:16px">
                <div class="field">
                    <label class="field__label" for="f-pass">
                        Пароль <?= $id === 0 ? '<span class="req">*</span>' : '<small>(оставьте пустым, чтобы не менять)</small>' ?>
                    </label>
                    <input class="field__input" type="password" id="f-pass" name="password"
                           autocomplete="new-password" <?= $id === 0 ? 'required' : '' ?>>
                </div>
                <div class="field">
                    <label class="field__label" for="f-pass2">Повторите пароль</label>
                    <input class="field__input" type="password" id="f-pass2" name="password_confirm"
                           autocomplete="new-password">
                    <p class="field__hint">Минимум 8 символов, буквы и цифры.</p>
                </div>
            </div>

            <label class="check" style="margin-top:16px">
                <input type="checkbox" name="is_active" <?= $user['is_active'] ? 'checked' : '' ?>
                    <?= $id > 0 && $id === Auth::id() ? 'disabled' : '' ?>>
                Учётная запись активна
            </label>
            <?php if ($id > 0 && $id === Auth::id()): ?>
                <p class="field__hint">Собственную учётную запись отключить нельзя.</p>
            <?php endif; ?>

            <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
                <button class="btn btn--accent" type="submit">Сохранить</button>
                <a class="btn btn--ghost" href="<?= Helpers::e(Helpers::url('admin/users.php')) ?>">Отмена</a>
            </div>
        </form>
    </div>
</div>

<?php require APP_ROOT . '/admin/partials/footer.php'; ?>
