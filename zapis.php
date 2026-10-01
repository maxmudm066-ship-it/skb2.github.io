<?php
/**
 * Мастер онлайн-записи на приём (5 шагов):
 *   1 — направление, 2 — врач, 3 — дата и время, 4 — данные пациента,
 *   5 — подтверждение с номером заявки.
 *
 * Поддерживается предвыбор врача из карточки: zapis.php?doctor=N
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = __('bk.title');
$pageDesc  = __('bk.lead');
$pageCss   = 'assets/css/booking.css';
$pageJs    = 'assets/js/booking.js';
$bodyClass = 'page-booking';

/* ---- Шаг 1: активные отделения с числом доступных врачей ---- */
$st = Database::get()->query(
    "SELECT dep.id, dep.name_ru, dep.name_uz, dep.name_en, dep.icon,
            (SELECT COUNT(*) FROM doctors d
             WHERE d.department_id = dep.id AND d.is_active = 1) AS doctors_count
     FROM departments dep
     WHERE dep.is_active = 1
     ORDER BY dep.sort_order, dep.id"
);
$departments = $st->fetchAll();

/* ---- Предвыбор врача (?doctor=N) для перехода сразу к выбору даты ---- */
$preselect = null;
$doctorParam = filter_input(INPUT_GET, 'doctor', FILTER_VALIDATE_INT);
if ($doctorParam) {
    $st = Database::get()->prepare(
        "SELECT d.id AS doctor_id, d.department_id
         FROM doctors d
         JOIN departments dep ON dep.id = d.department_id
         WHERE d.id = ? AND d.is_active = 1 AND dep.is_active = 1"
    );
    $st->execute([$doctorParam]);
    $row = $st->fetch();
    if ($row) {
        $preselect = [
            'doctor_id'     => (int)$row['doctor_id'],
            'department_id' => (int)$row['department_id'],
        ];
    }
}

/* ---- Строки и данные для booking.js ---- */
$monthsNom = match (Lang::current()) {
    'uz' => ['Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun', 'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'],
    'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    default => ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
};
$monthsGen = [];
foreach (range(1, 12) as $m) {
    $monthsGen[] = Lang::t('month.' . $m);
}
$weekdays = match (Lang::current()) {
    'uz' => ['Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh', 'Ya'],
    'en' => ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'],
    default => ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
};

$bookingConfig = [
    'csrf'       => Csrf::token(),
    'preselect'  => $preselect,
    'today'      => date('Y-m-d'),
    'maxDate'    => date('Y-m-d', strtotime('+' . BOOKING_MAX_DAYS . ' days')),
    'urls'       => [
        'doctors'      => Helpers::url('api/get-doctors.php'),
        'availability' => Helpers::url('api/get-availability.php'),
        'slots'        => Helpers::url('api/get-slots.php'),
        'book'         => Helpers::url('api/book-appointment.php'),
        'home'         => Helpers::url('index.php'),
    ],
    'i18n'       => [
        'loading'      => __('common.loading'),
        'error'        => __('common.error'),
        'retry'        => __('common.retry'),
        'optional'     => __('common.optional'),
        'step'         => __('bk.step'),
        'no_doctors'   => __('bk.no_doctors'),
        'found'        => __('docs.found'),
        'choose_date'  => __('bk.choose_date'),
        'slots_title'  => __('bk.slots_title'),
        'no_slots'     => __('bk.no_slots'),
        'select_slot'  => __('bk.select_slot'),
        'today'        => __('bk.today'),
        'back'         => __('bk.back'),
        'next'         => __('bk.next'),
        'submitting'   => __('bk.submitting'),
        'errors'       => __('bk.errors'),
        'err_name'     => __('bk.err_name'),
        'err_phone'    => __('bk.err_phone'),
        'err_birth'    => __('bk.err_birth'),
        'err_passport' => __('bk.err_passport'),
        'err_pinfl'    => __('bk.err_pinfl'),
        'err_comment'  => __('bk.err_comment'),
        'err_consent'  => __('bk.err_consent'),
        'err_generic'  => __('bk.err_generic'),
        'err_rate'     => __('bk.err_rate'),
        'err_slot'     => __('bk.err_slot'),
        'err_day'      => __('bk.err_day'),
        'err_csrf'     => __('bk.err_csrf'),
        'err_honeypot' => __('bk.err_honeypot'),
        'done_title'   => __('bk.done_title'),
        'done_lead'    => __('bk.done_lead'),
        'ticket'       => __('bk.ticket'),
        'instr_title'  => __('bk.instr_title'),
        'i1'           => __('bk.i1'),
        'i2'           => __('bk.i2'),
        'i3'           => __('bk.i3'),
        'i4'           => __('bk.i4'),
        'i5'           => __('bk.i5'),
        'again'        => __('bk.again'),
        'home'         => __('bk.home'),
        'print'        => __('bk.print'),
        'summary'      => __('bk.summary'),
        'f_doctor'     => __('bk.f_doctor'),
        'f_dept'       => __('bk.f_dept'),
        'f_date'       => __('bk.f_date'),
        'f_time'       => __('bk.f_time'),
        'f_office'     => __('bk.f_office'),
        'f_patient'    => __('bk.f_patient'),
        'f_phone'      => __('bk.f_phone'),
        'change'       => __('bk.change'),
        'required_note'=> __('bk.required_note'),
    ],
    'months'     => $monthsNom,
    'monthsGen'  => $monthsGen,
    'weekdays'   => $weekdays,
];

require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="page-hero__title"><?= __('bk.title') ?></p>
        <p class="page-hero__lead"><?= __('bk.lead') ?></p>
    </div>
</section>

<div class="container booking" data-booking>
    <ol class="booking-progress" data-progress aria-hidden="true">
        <?php foreach ([__('bk.s1'), __('bk.s2'), __('bk.s3'), __('bk.s4'), __('bk.s5')] as $i => $label): ?>
            <li class="booking-progress__item<?= $i === 0 ? ' is-active' : '' ?>">
                <span class="booking-progress__num"><?= $i + 1 ?></span>
                <span class="booking-progress__label"><?= Helpers::e($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>

    <div class="booking-steps">

        <!-- Шаг 1: направление -->
        <section class="booking-step is-active" data-step="1" aria-label="<?= Helpers::e(__('bk.s1')) ?>">
            <h2 class="booking-step__title"><?= __('bk.s1') ?></h2>
            <div class="dept-tiles">
                <?php foreach ($departments as $dep):
                    $depName  = Lang::tx($dep, 'name');
                    $depCount = (int)$dep['doctors_count'];
                    $depIcon  = (string)($dep['icon'] ?? 'cross');
                    if (!in_array($depIcon, ['stethoscope', 'scalpel', 'heart', 'brain', 'scan', 'mother', 'child', 'eye', 'bone', 'flask'], true)) {
                        $depIcon = 'cross';
                    }
                    ?>
                    <button type="button"
                            class="dept-tile<?= $depCount === 0 ? ' is-empty' : '' ?>"
                            data-department-id="<?= (int)$dep['id'] ?>"
                            data-department-name="<?= Helpers::e($depName) ?>"
                            <?= $depCount === 0 ? 'disabled' : '' ?>>
                        <svg class="icon"><use href="#i-<?= Helpers::e($depIcon) ?>"/></svg>
                        <span class="dept-tile__name"><?= Helpers::e($depName) ?></span>
                        <span class="dept-tile__count"><?= (int)$depCount ?> <?= Helpers::e(Helpers::plural($depCount, 'doctor')) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Шаг 2: врач -->
        <section class="booking-step" data-step="2" hidden aria-label="<?= Helpers::e(__('bk.s2')) ?>">
            <h2 class="booking-step__title"><?= __('bk.s2') ?></h2>
            <div class="doctor-rows" data-doctors-list aria-live="polite"></div>
        </section>

        <!-- Шаг 3: дата и время -->
        <section class="booking-step" data-step="3" hidden aria-label="<?= Helpers::e(__('bk.s3')) ?>">
            <h2 class="booking-step__title"><?= __('bk.choose_date') ?></h2>
            <div class="booking-mini" data-mini-summary hidden></div>

            <div class="booking-datetime">
                <div class="cal" data-calendar></div>
                <div class="booking-slots" data-slots-panel aria-live="polite"></div>
            </div>

            <div class="booking-actions">
                <button type="button" class="btn btn--outline" data-back="<?= Helpers::e(__('bk.back')) ?>">
                    <svg class="icon icon--flip"><use href="#i-arrow"/></svg>
                    <span><?= __('bk.back') ?></span>
                </button>
            </div>
        </section>

        <!-- Шаг 4: данные пациента -->
        <section class="booking-step" data-step="4" hidden aria-label="<?= Helpers::e(__('bk.s4')) ?>">
            <h2 class="booking-step__title"><?= __('bk.s4') ?></h2>

            <div class="booking-form-wrap">
                <form id="booking-form" novalidate>
                    <!-- Honeypot: люди это поле не видят и не заполняют -->
                    <div class="hp-field" aria-hidden="true">
                        <label for="hp-website">Website</label>
                        <input type="text" id="hp-website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="form-alert" data-form-alert hidden></div>

                    <div class="field">
                        <label class="field__label" for="f-name"><?= __('bk.name') ?> <span class="req">*</span></label>
                        <input class="field__input" type="text" id="f-name" name="patient_name"
                               autocomplete="name" maxlength="150" placeholder="<?= Helpers::e(__('bk.name_ph')) ?>">
                        <p class="field__error" data-error-for="patient_name" hidden></p>
                    </div>

                    <div class="field">
                        <label class="field__label" for="f-phone"><?= __('bk.phone') ?> <span class="req">*</span></label>
                        <input class="field__input" type="tel" id="f-phone" name="patient_phone"
                               inputmode="tel" autocomplete="tel" placeholder="+998 (90) 123-45-67">
                        <p class="field__error" data-error-for="patient_phone" hidden></p>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label class="field__label" for="f-birth"><?= __('bk.birth') ?> <span class="opt">(<?= __('common.optional') ?>)</span></label>
                            <input class="field__input" type="date" id="f-birth" name="patient_birth_date"
                                   min="1900-01-01" max="<?= date('Y-m-d') ?>">
                            <p class="field__error" data-error-for="patient_birth_date" hidden></p>
                        </div>
                        <div class="field">
                            <label class="field__label" for="f-passport"><?= __('bk.passport') ?> <span class="opt">(<?= __('common.optional') ?>)</span></label>
                            <input class="field__input" type="text" id="f-passport" name="patient_passport"
                                   autocomplete="off" maxlength="9" placeholder="<?= Helpers::e(__('bk.passport_ph')) ?>">
                            <p class="field__error" data-error-for="patient_passport" hidden></p>
                        </div>
                    </div>

                    <div class="field">
                        <label class="field__label" for="f-pinfl"><?= __('bk.pinfl') ?> <span class="opt">(<?= __('common.optional') ?>)</span></label>
                        <input class="field__input" type="text" id="f-pinfl" name="patient_pinfl"
                               inputmode="numeric" autocomplete="off" maxlength="14" placeholder="<?= Helpers::e(__('bk.pinfl_ph')) ?>">
                        <p class="field__error" data-error-for="patient_pinfl" hidden></p>
                    </div>

                    <div class="field">
                        <label class="field__label" for="f-comment"><?= __('bk.comment') ?> <span class="opt">(<?= __('common.optional') ?>)</span></label>
                        <textarea class="field__input" id="f-comment" name="patient_comment" rows="4"
                                  maxlength="1000" placeholder="<?= Helpers::e(__('bk.comment_ph')) ?>"></textarea>
                        <p class="field__error" data-error-for="patient_comment" hidden></p>
                    </div>

                    <div class="field field--check">
                        <label class="check">
                            <input type="checkbox" name="consent" value="1">
                            <span class="check__box" aria-hidden="true"><svg class="icon"><use href="#i-check"/></svg></span>
                            <span class="check__label"><?= __('bk.consent') ?></span>
                        </label>
                        <p class="field__error" data-error-for="consent" hidden></p>
                    </div>

                    <div class="booking-actions booking-actions--form">
                        <button type="button" class="btn btn--outline" data-back="<?= Helpers::e(__('bk.back')) ?>">
                            <svg class="icon icon--flip"><use href="#i-arrow"/></svg>
                            <span><?= __('bk.back') ?></span>
                        </button>
                        <button type="submit" class="btn btn--accent" data-submit>
                            <svg class="icon"><use href="#i-check"/></svg>
                            <span><?= __('bk.submit') ?></span>
                        </button>
                    </div>
                    <p class="booking-note"><?= __('bk.required_note') ?></p>
                </form>

                <aside class="booking-summary" data-summary hidden>
                    <h3><?= __('bk.summary') ?></h3>
                    <dl data-summary-rows></dl>
                </aside>
            </div>
        </section>

        <!-- Шаг 5: подтверждение -->
        <section class="booking-step" data-step="5" hidden aria-label="<?= Helpers::e(__('bk.s5')) ?>">
            <div class="booking-done" data-done></div>
        </section>
    </div>

    <p class="booking-stepinfo" data-step-info aria-live="polite"><?= Helpers::e(__('bk.step', 1)) ?></p>
</div>

<script>
window.CKB2_BOOKING = <?= json_encode($bookingConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
