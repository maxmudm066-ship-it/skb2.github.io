<?php

/**
 * Карточка врача. Ожидает массив $doctor со полями таблицы doctors
 * (при необходимости — с name_ru/name_uz/name_en, position_ru/…).
 */

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Forbidden');
}

$doctor = $doctor ?? null;
if (!$doctor) {
    return;
}

$docName     = Lang::tx($doctor, 'name');
$docPosition = Lang::tx($doctor, 'position');
$docPhoto    = (string)($doctor['photo'] ?? '');
$docOffice   = (string)($doctor['office'] ?? '');
$docExp      = (int)($doctor['experience_years'] ?? 0);
$docCategory = (string)($doctor['category'] ?? '');
?>
<article class="doctor-card">
    <a class="doctor-card__photo" href="<?= Helpers::url('zapis.php?doctor=' . (int)$doctor['id']) ?>">
        <?php if ($docPhoto !== '' && is_file(UPLOAD_DIR . '/' . $docPhoto)): ?>
            <img src="<?= Helpers::e(Helpers::url('uploads/' . $docPhoto)) ?>" alt="<?= Helpers::e($docName) ?>" loading="lazy" width="280" height="320">
        <?php else: ?>
            <span class="doctor-card__initials"><?= Helpers::e(Helpers::initials($docName)) ?></span>
        <?php endif; ?>
    </a>
    <div class="doctor-card__body">
        <?php if ($docCategory !== ''): ?>
            <span class="doctor-card__category"><?= Helpers::e($docCategory) ?></span>
        <?php endif; ?>
        <h3 class="doctor-card__name"><?= Helpers::e($docName) ?></h3>
        <p class="doctor-card__position"><?= Helpers::e($docPosition) ?></p>
        <ul class="doctor-card__meta">
            <?php if ($docExp > 0): ?>
                <li>
                    <svg class="icon"><use href="#i-clock"/></svg>
                    <span><?= Helpers::e(__('docs.experience', $docExp . ' ' . Helpers::plural($docExp, 'year'))) ?></span>
                </li>
            <?php endif; ?>
            <?php if ($docOffice !== ''): ?>
                <li>
                    <svg class="icon"><use href="#i-pin"/></svg>
                    <span><?= Helpers::e(__('docs.office', $docOffice)) ?></span>
                </li>
            <?php endif; ?>
        </ul>
        <a class="btn btn--outline btn--sm doctor-card__btn" href="<?= Helpers::url('zapis.php?doctor=' . (int)$doctor['id']) ?>">
            <svg class="icon"><use href="#i-calendar"/></svg>
            <span><?= __('docs.book') ?></span>
        </a>
    </div>
</article>
