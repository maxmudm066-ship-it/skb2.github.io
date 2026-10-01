<?php
/**
 * Настройки сайта: телефоны, e-mail, адрес, режим работы, примечание регистратуры.
 * Многоязычные поля редактируются для трёх языков.
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

Auth::requireAdmin();

$db = Database::get();

$keys = [
    'site_phone_main',
    'site_phone_registry',
    'site_phone_emergency',
    'site_email',
    'site_address_ru', 'site_address_uz', 'site_address_en',
    'working_hours_ru', 'working_hours_uz', 'working_hours_en',
    'registry_note_ru', 'registry_note_uz', 'registry_note_en',
];

$limits = [
    'site_phone_main'      => 20,
    'site_phone_registry'  => 20,
    'site_phone_emergency' => 10,
    'site_email'           => 190,
    'site_address_ru'      => 500, 'site_address_uz' => 500, 'site_address_en' => 500,
    'working_hours_ru'     => 200, 'working_hours_uz' => 200, 'working_hours_en' => 200,
    'registry_note_ru'     => 1000, 'registry_note_uz' => 1000, 'registry_note_en' => 1000,
];

/* ---- Сохранение ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verifyRequest()) {
        Helpers::flashSet('error', 'Недействительный CSRF-токен. Повторите действие.');
        Helpers::redirect(Helpers::url('admin/settings.php'));
    }

    $errors = [];
    $values = [];

    /* телефоны регистратуры и общий — строго формат Узбекистана */
    foreach (['site_phone_main', 'site_phone_registry'] as $k) {
        $normalized = Helpers::normalizePhone(trim((string)($_POST[$k] ?? '')));
        if ($normalized === null) {
            $errors[] = 'Телефон должен быть в формате +998 (XX) XXX-XX-XX.';
        } else {
            $values[$k] = $normalized;
        }
    }

    /* экстренный номер допускает короткие номера (103, 112…) */
    $emergency = preg_replace('/[^0-9+]/', '', (string)($_POST['site_phone_emergency'] ?? ''));
    if ($emergency === '' || mb_strlen($emergency) > 10) {
        $errors[] = 'Экстренный номер — от 1 до 10 символов (например, 103).';
    } else {
        $values['site_phone_emergency'] = $emergency;
    }

    $email = trim((string)($_POST['site_email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный адрес электронной почты.';
    } elseif (mb_strlen($email) > 190) {
        $errors[] = 'E-mail — не более 190 символов.';
    } else {
        $values['site_email'] = $email;
    }

    foreach (['site_address_ru', 'site_address_uz', 'site_address_en',
              'working_hours_ru', 'working_hours_uz', 'working_hours_en',
              'registry_note_ru', 'registry_note_uz', 'registry_note_en'] as $k) {
        $v = trim((string)($_POST[$k] ?? ''));
        if (mb_strlen($v) > $limits[$k]) {
            $errors[] = 'Поле «' . $k . '» — не более ' . $limits[$k] . ' символов.';
        } else {
            $values[$k] = $v;
        }
    }

    if ($errors === []) {
        try {
            $db->beginTransaction();
            foreach ($values as $k => $v) {
                Settings::set($k, $v);
            }
            $db->commit();
            Helpers::flashSet('success', 'Настройки сайта сохранены.');
            Helpers::redirect(Helpers::url('admin/settings.php'));
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[CKB2] settings save: ' . $e->getMessage());
            Helpers::flashSet('error', 'Не удалось сохранить настройки. Проверьте журнал ошибок.');
            Helpers::redirect(Helpers::url('admin/settings.php'));
        }
    }

    /* при ошибках — вернуть введённые значения в форму */
    $settings = array_merge(
        array_combine($keys, array_map(static fn(string $k): string => Settings::get($k), $keys)),
        $values
    );
    $formErrors = $errors;
} else {
    $settings = [];
    foreach ($keys as $k) {
        $settings[$k] = Settings::get($k);
    }
}

$pageTitle = 'Настройки сайта';
$activeNav = 'settings';
require APP_ROOT . '/admin/partials/header.php';
?>

<div class="page-head">
    <h2>Настройки сайта</h2>
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

<form method="post" action="<?= Helpers::e(Helpers::url('admin/settings.php')) ?>">
    <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">

    <div class="card">
        <div class="card__body">
            <h3 class="card__title">Контакты</h3>
            <div class="form-grid">
                <div class="field">
                    <label class="field__label" for="f-phone-main">Основной телефон <span class="req">*</span></label>
                    <input class="field__input" type="tel" id="f-phone-main" name="site_phone_main"
                           maxlength="20" placeholder="+998 71 233-55-44"
                           value="<?= Helpers::e($settings['site_phone_main']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-phone-reg">Телефон регистратуры <span class="req">*</span></label>
                    <input class="field__input" type="tel" id="f-phone-reg" name="site_phone_registry"
                           maxlength="20" placeholder="+998 71 233-55-45"
                           value="<?= Helpers::e($settings['site_phone_registry']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-phone-em">Экстренный номер</label>
                    <input class="field__input" type="tel" id="f-phone-em" name="site_phone_emergency"
                           maxlength="10" placeholder="103"
                           value="<?= Helpers::e($settings['site_phone_emergency']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-email">Электронная почта</label>
                    <input class="field__input" type="email" id="f-email" name="site_email"
                           maxlength="190" placeholder="info@ckb2.uz"
                           value="<?= Helpers::e($settings['site_email']) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__body">
            <h3 class="card__title">Адрес больницы</h3>
            <div class="form-grid">
                <div class="field">
                    <label class="field__label" for="f-addr-ru">Адрес (рус.)</label>
                    <input class="field__input" type="text" id="f-addr-ru" name="site_address_ru"
                           maxlength="500" value="<?= Helpers::e($settings['site_address_ru']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-addr-uz">Адрес (узб.)</label>
                    <input class="field__input" type="text" id="f-addr-uz" name="site_address_uz"
                           maxlength="500" value="<?= Helpers::e($settings['site_address_uz']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-addr-en">Адрес (англ.)</label>
                    <input class="field__input" type="text" id="f-addr-en" name="site_address_en"
                           maxlength="500" value="<?= Helpers::e($settings['site_address_en']) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__body">
            <h3 class="card__title">Режим работы</h3>
            <div class="form-grid">
                <div class="field">
                    <label class="field__label" for="f-wh-ru">Часы работы (рус.)</label>
                    <input class="field__input" type="text" id="f-wh-ru" name="working_hours_ru"
                           maxlength="200" placeholder="Пн–Пт 08:00–18:00"
                           value="<?= Helpers::e($settings['working_hours_ru']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-wh-uz">Часы работы (узб.)</label>
                    <input class="field__input" type="text" id="f-wh-uz" name="working_hours_uz"
                           maxlength="200" value="<?= Helpers::e($settings['working_hours_uz']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-wh-en">Часы работы (англ.)</label>
                    <input class="field__input" type="text" id="f-wh-en" name="working_hours_en"
                           maxlength="200" placeholder="Mon–Fri 08:00–18:00"
                           value="<?= Helpers::e($settings['working_hours_en']) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__body">
            <h3 class="card__title">Примечание регистратуры</h3>
            <p class="field__hint" style="margin-top:0">
                Отображается на странице онлайн-записи под контактным телефоном.
            </p>
            <div class="form-grid">
                <div class="field">
                    <label class="field__label" for="f-rn-ru">Примечание (рус.)</label>
                    <textarea class="field__textarea" id="f-rn-ru" name="registry_note_ru" rows="3"
                              maxlength="1000"><?= Helpers::e($settings['registry_note_ru']) ?></textarea>
                </div>
                <div class="field">
                    <label class="field__label" for="f-rn-uz">Примечание (узб.)</label>
                    <textarea class="field__textarea" id="f-rn-uz" name="registry_note_uz" rows="3"
                              maxlength="1000"><?= Helpers::e($settings['registry_note_uz']) ?></textarea>
                </div>
                <div class="field">
                    <label class="field__label" for="f-rn-en">Примечание (англ.)</label>
                    <textarea class="field__textarea" id="f-rn-en" name="registry_note_en" rows="3"
                              maxlength="1000"><?= Helpers::e($settings['registry_note_en']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:4px">
        <button class="btn btn--accent" type="submit">Сохранить настройки</button>
    </div>
</form>

<?php require APP_ROOT . '/admin/partials/footer.php'; ?>
