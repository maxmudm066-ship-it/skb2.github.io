<?php
/**
 * Карточка врача: создание и редактирование.
 * Фото загружается через Upload::image() (проверка MIME, случайное имя).
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

Auth::requireAdmin();

$db = Database::get();
$id = (int)($_GET['id'] ?? 0);

/* ---- Загрузка текущих данных ---- */
$doctor = [
    'id' => 0, 'department_id' => 0, 'name_ru' => '', 'name_uz' => '', 'name_en' => '',
    'position_ru' => '', 'position_uz' => '', 'position_en' => '',
    'category' => '', 'experience_years' => 0, 'office' => '', 'photo' => '',
    'bio' => '', 'slot_duration' => 30, 'is_active' => 1, 'sort_order' => 0,
];
if ($id > 0) {
    $st = $db->prepare("SELECT * FROM doctors WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        Helpers::flashSet('error', 'Врач не найден.');
        Helpers::redirect(Helpers::url('admin/doctors.php'));
    }
    $doctor = array_merge($doctor, $row);
}

/* ---- Отделения для выбора ---- */
$departments = [];
$st = $db->query("SELECT id, name_ru FROM departments WHERE is_active = 1 ORDER BY sort_order, name_ru");
while ($r = $st->fetch()) {
    $departments[] = $r;
}

/* ---- Сохранение ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verifyRequest()) {
        Helpers::flashSet('error', 'Недействительный CSRF-токен. Повторите действие.');
        Helpers::redirect(Helpers::url($id > 0 ? 'admin/doctor-form.php?id=' . $id : 'admin/doctor-form.php'));
    }

    $in = $_POST;
    $errors = [];

    $departmentId = (int)($in['department_id'] ?? 0);
    $nameRu = trim((string)($in['name_ru'] ?? ''));
    $positionRu = trim((string)($in['position_ru'] ?? ''));
    $category = trim((string)($in['category'] ?? ''));
    $experience = (int)($in['experience_years'] ?? 0);
    $office = trim((string)($in['office'] ?? ''));
    $bio = trim((string)($in['bio'] ?? ''));
    $slotDuration = (int)($in['slot_duration'] ?? 30);
    $isActive = isset($in['is_active']) ? 1 : 0;
    $sortOrder = (int)($in['sort_order'] ?? 0);

    $nameUz = trim((string)($in['name_uz'] ?? ''));
    $nameEn = trim((string)($in['name_en'] ?? ''));
    $positionUz = trim((string)($in['position_uz'] ?? ''));
    $positionEn = trim((string)($in['position_en'] ?? ''));

    $st = $db->prepare("SELECT COUNT(*) FROM departments WHERE id = ? AND is_active = 1");
    $st->execute([$departmentId]);
    if ($departmentId <= 0 || (int)$st->fetchColumn() === 0) {
        $errors[] = 'Укажите отделение.';
    }
    if (mb_strlen($nameRu) < 3 || mb_strlen($nameRu) > 200) {
        $errors[] = 'ФИО (рус.) — обязательно, от 3 до 200 символов.';
    }
    if ($positionRu === '' || mb_strlen($positionRu) > 200) {
        $errors[] = 'Должность (рус.) — обязательное поле.';
    }
    if (!in_array($slotDuration, [15, 30, 60], true)) {
        $errors[] = 'Длительность слота: 15, 30 или 60 минут.';
    }
    if ($experience < 0 || $experience > 70) {
        $errors[] = 'Стаж — от 0 до 70 лет.';
    }
    if (mb_strlen($office) > 20) {
        $errors[] = 'Номер кабинета — не более 20 символов.';
    }
    if (mb_strlen($category) > 100) {
        $errors[] = 'Категория — не более 100 символов.';
    }
    if (mb_strlen($bio) > 5000) {
        $errors[] = 'Биография — не более 5000 символов.';
    }

    /* ---- Загрузка фото ---- */
    $photo = $doctor['photo'];
    $newPhoto = null;
    if (isset($_FILES['photo']) && (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $newPhoto = Upload::image($_FILES['photo'], 'doctors', $photoError);
        if ($newPhoto === null && $photoError !== null) {
            $errors[] = $photoError;
        }
    }

    if ($errors === []) {
        try {
            if ($id > 0) {
                $st = $db->prepare(
                    "UPDATE doctors SET department_id=?, name_ru=?, name_uz=?, name_en=?,
                         position_ru=?, position_uz=?, position_en=?, category=?,
                         experience_years=?, office=?, photo=?, bio=?,
                         slot_duration=?, is_active=?, sort_order=?
                     WHERE id=?"
                );
                $st->execute([
                    $departmentId, $nameRu, $nameUz, $nameEn,
                    $positionRu, $positionUz, $positionEn, $category,
                    $experience, $office, $newPhoto ?? $photo, $bio,
                    $slotDuration, $isActive, $sortOrder, $id,
                ]);
            } else {
                $st = $db->prepare(
                    "INSERT INTO doctors (department_id, name_ru, name_uz, name_en,
                         position_ru, position_uz, position_en, category,
                         experience_years, office, photo, bio,
                         slot_duration, is_active, sort_order)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                );
                $st->execute([
                    $departmentId, $nameRu, $nameUz, $nameEn,
                    $positionRu, $positionUz, $positionEn, $category,
                    $experience, $office, $newPhoto ?? $photo, $bio,
                    $slotDuration, $isActive, $sortOrder,
                ]);
                $id = (int)$db->lastInsertId();
            }

            /* старое фото удаляем только после успешного сохранения */
            if ($newPhoto !== null && $doctor['photo'] && $doctor['photo'] !== $newPhoto) {
                Upload::delete((string)$doctor['photo']);
            }

            Helpers::flashSet('success', 'Данные врача сохранены.');
            Helpers::redirect(Helpers::url('admin/doctors.php'));
        } catch (PDOException $e) {
            if ($newPhoto !== null) {
                Upload::delete($newPhoto);
            }
            throw $e;
        }
    }

    /* при ошибках — вернуть введённые значения в форму */
    $doctor = array_merge($doctor, [
        'department_id' => $departmentId, 'name_ru' => $nameRu, 'name_uz' => $nameUz,
        'name_en' => $nameEn, 'position_ru' => $positionRu, 'position_uz' => $positionUz,
        'position_en' => $positionEn, 'category' => $category, 'experience_years' => $experience,
        'office' => $office, 'bio' => $bio, 'slot_duration' => $slotDuration,
        'is_active' => $isActive, 'sort_order' => $sortOrder,
        'photo' => $newPhoto ?? $photo,
    ]);
    $formErrors = $errors;
}

$pageTitle = $id > 0 ? 'Врач: ' . $doctor['name_ru'] : 'Новый врач';
$activeNav = 'doctors';
require APP_ROOT . '/admin/partials/header.php';
?>

<div class="page-head">
    <h2><?= $id > 0 ? 'Редактирование врача' : 'Новый врач' ?></h2>
    <div class="page-head__spacer"></div>
    <div class="page-head__actions">
        <a class="btn btn--ghost btn--sm" href="<?= Helpers::e(Helpers::url('admin/doctors.php')) ?>">
            &larr; К списку врачей
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
        <form method="post" action="<?= Helpers::e(Helpers::url('admin/doctor-form.php' . ($id > 0 ? '?id=' . $id : ''))) ?>"
              enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">

            <div class="form-grid">
                <div class="field">
                    <label class="field__label" for="f-dept">Отделение <span class="req">*</span></label>
                    <select class="field__select" id="f-dept" name="department_id" required>
                        <option value="">— выберите —</option>
                        <?php foreach ($departments as $dep): ?>
                            <option value="<?= (int)$dep['id'] ?>" <?= (int)$doctor['department_id'] === (int)$dep['id'] ? 'selected' : '' ?>>
                                <?= Helpers::e($dep['name_ru']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="f-cat">Категория <small>(высшая, первая…)</small></label>
                    <input class="field__input" type="text" id="f-cat" name="category" maxlength="100"
                           value="<?= Helpers::e($doctor['category']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-exp">Стаж, лет</label>
                    <input class="field__input" type="number" id="f-exp" name="experience_years"
                           min="0" max="70" value="<?= (int)$doctor['experience_years'] ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-office">Кабинет</label>
                    <input class="field__input" type="text" id="f-office" name="office" maxlength="20"
                           placeholder="204" value="<?= Helpers::e($doctor['office']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-slot">Длительность слота</label>
                    <select class="field__select" id="f-slot" name="slot_duration">
                        <?php foreach ([15 => '15 минут', 30 => '30 минут', 60 => '60 минут'] as $min => $label): ?>
                            <option value="<?= $min ?>" <?= (int)$doctor['slot_duration'] === $min ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="f-sort">Порядок сортировки</label>
                    <input class="field__input" type="number" id="f-sort" name="sort_order"
                           value="<?= (int)$doctor['sort_order'] ?>">
                </div>
            </div>

            <div class="form-grid" style="margin-top:16px">
                <div class="field">
                    <label class="field__label" for="f-name-ru">ФИО (рус.) <span class="req">*</span></label>
                    <input class="field__input" type="text" id="f-name-ru" name="name_ru" maxlength="200"
                           required value="<?= Helpers::e($doctor['name_ru']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-name-uz">ФИО (узб.)</label>
                    <input class="field__input" type="text" id="f-name-uz" name="name_uz" maxlength="200"
                           value="<?= Helpers::e($doctor['name_uz']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-name-en">ФИО (англ.)</label>
                    <input class="field__input" type="text" id="f-name-en" name="name_en" maxlength="200"
                           value="<?= Helpers::e($doctor['name_en']) ?>">
                </div>
            </div>

            <div class="form-grid" style="margin-top:16px">
                <div class="field">
                    <label class="field__label" for="f-pos-ru">Должность (рус.) <span class="req">*</span></label>
                    <input class="field__input" type="text" id="f-pos-ru" name="position_ru" maxlength="200"
                           required value="<?= Helpers::e($doctor['position_ru']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-pos-uz">Должность (узб.)</label>
                    <input class="field__input" type="text" id="f-pos-uz" name="position_uz" maxlength="200"
                           value="<?= Helpers::e($doctor['position_uz']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-pos-en">Должность (англ.)</label>
                    <input class="field__input" type="text" id="f-pos-en" name="position_en" maxlength="200"
                           value="<?= Helpers::e($doctor['position_en']) ?>">
                </div>
            </div>

            <div class="field" style="margin-top:16px">
                <label class="field__label" for="f-bio">Биография</label>
                <textarea class="field__textarea" id="f-bio" name="bio" rows="5"><?= Helpers::e($doctor['bio']) ?></textarea>
            </div>

            <div class="form-grid" style="margin-top:16px">
                <div class="field">
                    <label class="field__label" for="f-photo">Фотография <small>(JPG, PNG, WEBP, до 4 МБ)</small></label>
                    <input class="field__input" type="file" id="f-photo" name="photo"
                           accept="image/jpeg,image/png,image/webp">
                    <p class="field__hint">
                        <?php if ($doctor['photo']): ?>
                            Текущее фото будет заменено при загрузке нового.
                        <?php else: ?>
                            Без фото на сайте отобразятся инициалы.
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($doctor['photo']): ?>
                    <div class="field">
                        <span class="field__label">Текущее фото</span>
                        <img class="thumb" src="<?= Helpers::e(Helpers::url('uploads/' . $doctor['photo'])) ?>"
                             alt="<?= Helpers::e($doctor['name_ru']) ?>" style="height:120px">
                    </div>
                <?php endif; ?>
            </div>

            <label class="check" style="margin-top:16px">
                <input type="checkbox" name="is_active" <?= $doctor['is_active'] ? 'checked' : '' ?>>
                Врач активен и доступен для онлайн-записи
            </label>

            <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
                <button class="btn btn--accent" type="submit">Сохранить</button>
                <a class="btn btn--ghost" href="<?= Helpers::e(Helpers::url('admin/doctors.php')) ?>">Отмена</a>
            </div>
        </form>
    </div>
</div>

<?php require APP_ROOT . '/admin/partials/footer.php'; ?>
