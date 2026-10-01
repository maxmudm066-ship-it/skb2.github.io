<?php
/**
 * Управление отделениями: список + форма создания/редактирования на одной странице.
 * Удаление отделения с врачами блокируется внешним ключом (FK RESTRICT).
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

Auth::requireAdmin();

$db = Database::get();

$iconWhitelist = [
    'stethoscope', 'scalpel', 'heart', 'brain', 'scan', 'mother',
    'child', 'eye', 'bone', 'flask', 'stetho', 'users', 'pulse', 'cross', 'doc',
];

/* ---- POST: сохранение / удаление ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verifyRequest()) {
        Helpers::flashSet('error', 'Недействительный CSRF-токен. Повторите действие.');
        Helpers::redirect(Helpers::url('admin/departments.php'));
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $nameRu = trim((string)($_POST['name_ru'] ?? ''));
        $nameUz = trim((string)($_POST['name_uz'] ?? ''));
        $nameEn = trim((string)($_POST['name_en'] ?? ''));
        $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
        $descRu = trim((string)($_POST['description_ru'] ?? ''));
        $descUz = trim((string)($_POST['description_uz'] ?? ''));
        $descEn = trim((string)($_POST['description_en'] ?? ''));
        $icon = (string)($_POST['icon'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $errors = [];
        if (mb_strlen($nameRu) < 2 || mb_strlen($nameRu) > 200) {
            $errors[] = 'Название (рус.) — обязательно, от 2 до 200 символов.';
        }
        if (!preg_match('/^[a-z0-9-]{1,100}$/', $slug)) {
            $errors[] = 'Слаг — латинские буквы, цифры и дефис (до 100 символов).';
        }
        if (!in_array($icon, $iconWhitelist, true)) {
            $icon = 'cross';
        }
        foreach (['name_uz' => $nameUz, 'name_en' => $nameEn] as $v) {
            if (mb_strlen($v) > 200) {
                $errors[] = 'Название — не более 200 символов.';
            }
        }

        if ($errors === []) {
            try {
                if ($id > 0) {
                    $st = $db->prepare(
                        "UPDATE departments SET name_ru=?, name_uz=?, name_en=?, slug=?,
                             description_ru=?, description_uz=?, description_en=?,
                             icon=?, sort_order=?, is_active=?
                         WHERE id=?"
                    );
                    $st->execute([
                        $nameRu, $nameUz, $nameEn, $slug,
                        $descRu, $descUz, $descEn,
                        $icon, $sortOrder, $isActive, $id,
                    ]);
                } else {
                    $st = $db->prepare(
                        "INSERT INTO departments (name_ru, name_uz, name_en, slug,
                             description_ru, description_uz, description_en,
                             icon, sort_order, is_active)
                         VALUES (?,?,?,?,?,?,?,?,?,?)"
                    );
                    $st->execute([
                        $nameRu, $nameUz, $nameEn, $slug,
                        $descRu, $descUz, $descEn,
                        $icon, $sortOrder, $isActive,
                    ]);
                }
                Helpers::flashSet('success', 'Отделение сохранено.');
                Helpers::redirect(Helpers::url('admin/departments.php'));
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    Helpers::flashSet('error', 'Отделение с таким слагом уже существует.');
                } else {
                    throw $e;
                }
            }
        } else {
            Helpers::flashSet('error', implode(' ', $errors));
            Helpers::redirect(Helpers::url('admin/departments.php' . ($id > 0 ? '?edit=' . $id : '')));
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $st = $db->prepare("DELETE FROM departments WHERE id = ?");
            $st->execute([$id]);
            Helpers::flashSet('success', 'Отделение удалено.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                Helpers::flashSet('error', 'Нельзя удалить отделение: в нём числятся врачи. Сначала перенесите или удалите их.');
            } else {
                throw $e;
            }
        }
        Helpers::redirect(Helpers::url('admin/departments.php'));
    }
}

/* ---- Список ---- */
$departments = [];
$st = $db->query(
    "SELECT d.*,
            (SELECT COUNT(*) FROM doctors doc WHERE doc.department_id = d.id) AS doctors_cnt
     FROM departments d
     ORDER BY d.sort_order, d.name_ru"
);
while ($r = $st->fetch()) {
    $departments[] = $r;
}

/* ---- Данные редактируемой формы ---- */
$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId > 0) {
    foreach ($departments as $d) {
        if ((int)$d['id'] === $editId) {
            $editing = $d;
            break;
        }
    }
}

$pageTitle = 'Отделения';
$activeNav = 'departments';
require APP_ROOT . '/admin/partials/header.php';
?>

<div class="page-head">
    <h2>Отделения <span class="table__muted">(<?= count($departments) ?>)</span></h2>
    <div class="page-head__spacer"></div>
    <div class="page-head__actions">
        <a class="btn btn--ghost btn--sm" href="<?= Helpers::e(Helpers::url('admin/departments.php')) ?>">Новая запись</a>
    </div>
</div>

<div class="card">
    <div class="card__head">
        <h3 class="card__title"><?= $editing ? 'Редактирование: ' . Helpers::e($editing['name_ru']) : 'Новое отделение' ?></h3>
    </div>
    <div class="card__body">
        <form method="post" action="<?= Helpers::e(Helpers::url('admin/departments.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

            <div class="form-grid">
                <div class="field">
                    <label class="field__label" for="dep-name-ru">Название (рус.) <span class="req">*</span></label>
                    <input class="field__input" type="text" id="dep-name-ru" name="name_ru" maxlength="200"
                           required value="<?= Helpers::e($editing['name_ru'] ?? '') ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="dep-name-uz">Название (узб.)</label>
                    <input class="field__input" type="text" id="dep-name-uz" name="name_uz" maxlength="200"
                           value="<?= Helpers::e($editing['name_uz'] ?? '') ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="dep-name-en">Название (англ.)</label>
                    <input class="field__input" type="text" id="dep-name-en" name="name_en" maxlength="200"
                           value="<?= Helpers::e($editing['name_en'] ?? '') ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="dep-slug">Слаг (URL) <span class="req">*</span></label>
                    <input class="field__input" type="text" id="dep-slug" name="slug" maxlength="100"
                           required pattern="[a-z0-9-]+" placeholder="cardiology"
                           value="<?= Helpers::e($editing['slug'] ?? '') ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="dep-icon">Иконка</label>
                    <select class="field__select" id="dep-icon" name="icon">
                        <?php foreach ($iconWhitelist as $ic): ?>
                            <option value="<?= $ic ?>" <?= ($editing['icon'] ?? '') === $ic ? 'selected' : '' ?>>
                                <?= $ic ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="dep-sort">Порядок сортировки</label>
                    <input class="field__input" type="number" id="dep-sort" name="sort_order"
                           value="<?= (int)($editing['sort_order'] ?? 0) ?>">
                </div>
            </div>

            <div class="form-grid" style="margin-top:16px">
                <div class="field">
                    <label class="field__label" for="dep-desc-ru">Описание (рус.)</label>
                    <textarea class="field__textarea" id="dep-desc-ru" name="description_ru"
                              rows="3"><?= Helpers::e($editing['description_ru'] ?? '') ?></textarea>
                </div>
                <div class="field">
                    <label class="field__label" for="dep-desc-uz">Описание (узб.)</label>
                    <textarea class="field__textarea" id="dep-desc-uz" name="description_uz"
                              rows="3"><?= Helpers::e($editing['description_uz'] ?? '') ?></textarea>
                </div>
                <div class="field">
                    <label class="field__label" for="dep-desc-en">Описание (англ.)</label>
                    <textarea class="field__textarea" id="dep-desc-en" name="description_en"
                              rows="3"><?= Helpers::e($editing['description_en'] ?? '') ?></textarea>
                </div>
            </div>

            <label class="check" style="margin-top:16px">
                <input type="checkbox" name="is_active" <?= (int)($editing['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                Отделение активно и отображается на сайте
            </label>

            <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
                <button class="btn btn--accent" type="submit">Сохранить</button>
                <?php if ($editing): ?>
                    <a class="btn btn--ghost" href="<?= Helpers::e(Helpers::url('admin/departments.php')) ?>">Отмена</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card__head">
        <h3 class="card__title">Список отделений</h3>
    </div>
    <?php if ($departments === []): ?>
        <div class="empty">Отделения не добавлены.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Отделение</th>
                    <th>Слаг</th>
                    <th>Врачей</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($departments as $d): ?>
                    <tr>
                        <td>
                            <?= Helpers::e($d['name_ru']) ?>
                            <?php if ($d['name_en']): ?>
                                <div class="table__muted"><?= Helpers::e($d['name_en']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= Helpers::e($d['slug']) ?></td>
                        <td class="num"><?= (int)$d['doctors_cnt'] ?></td>
                        <td>
                            <span class="badge badge--<?= $d['is_active'] ? 'completed' : 'muted' ?>">
                                <?= $d['is_active'] ? 'Активно' : 'Скрыто' ?>
                            </span>
                        </td>
                        <td>
                            <div class="table__actions">
                                <a class="btn btn--outline btn--sm" href="<?= Helpers::e(Helpers::url('admin/departments.php?edit=' . (int)$d['id'])) ?>">
                                    <svg class="icon"><use href="#a-edit"/></svg>
                                    Изменить
                                </a>
                                <form method="post" action="<?= Helpers::e(Helpers::url('admin/departments.php')) ?>"
                                      data-confirm="Удалить отделение «<?= Helpers::e($d['name_ru']) ?>»?">
                                    <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
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
