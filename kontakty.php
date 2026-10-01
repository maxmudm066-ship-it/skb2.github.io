<?php

/**
 * Контакты и реквизиты больницы.
 */

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = __('contacts.page_title');
$pageDesc  = __('contacts.page_lead');

$phoneMain     = Settings::get('site_phone_main');
$phoneRegistry = Settings::get('site_phone_registry');
$phoneEmergency= Settings::get('site_phone_emergency');
$email         = Settings::get('site_email');
$address       = Settings::getL('site_address');
$hours         = Settings::getL('working_hours');

require APP_ROOT . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1><?= Helpers::e(__('contacts.page_title')) ?></h1>
        <p><?= Helpers::e(__('contacts.page_lead')) ?></p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="contact-grid">
            <div class="contact-card">
                <span class="contact-card__icon"><svg class="icon"><use href="#i-phone"/></svg></span>
                <h2><?= Helpers::e(__('contacts.registry')) ?></h2>
                <a class="contact-card__value" href="tel:<?= Helpers::e(preg_replace('/\D+/', '', $phoneRegistry)) ?>">
                    <?= Helpers::e(Helpers::formatPhone($phoneRegistry)) ?>
                </a>
                <a class="contact-card__value contact-card__value--muted" href="tel:<?= Helpers::e(preg_replace('/\D+/', '', $phoneMain)) ?>">
                    <?= Helpers::e(Helpers::formatPhone($phoneMain)) ?>
                </a>
            </div>

            <div class="contact-card contact-card--alert">
                <span class="contact-card__icon"><svg class="icon"><use href="#i-alert"/></svg></span>
                <h2><?= Helpers::e(__('contacts.emergency')) ?></h2>
                <a class="contact-card__value" href="tel:<?= Helpers::e($phoneEmergency) ?>">
                    <?= Helpers::e($phoneEmergency) ?>
                </a>
            </div>

            <div class="contact-card">
                <span class="contact-card__icon"><svg class="icon"><use href="#i-mail"/></svg></span>
                <h2><?= Helpers::e(__('contacts.email')) ?></h2>
                <a class="contact-card__value" href="mailto:<?= Helpers::e($email) ?>"><?= Helpers::e($email) ?></a>
            </div>

            <div class="contact-card">
                <span class="contact-card__icon"><svg class="icon"><use href="#i-pin"/></svg></span>
                <h2><?= Helpers::e(__('contacts.address')) ?></h2>
                <p class="contact-card__value"><?= Helpers::e($address) ?></p>
            </div>

            <div class="contact-card">
                <span class="contact-card__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
                <h2><?= Helpers::e(__('contacts.hours')) ?></h2>
                <p class="contact-card__value"><?= Helpers::e($hours) ?></p>
            </div>
        </div>

        <div class="contact-map">
            <div class="contact-map__body">
                <h2><?= Helpers::e(__('contacts.map_title')) ?></h2>
                <p><?= Helpers::e(__('contacts.map_text')) ?></p>
                <a class="btn btn--primary" href="https://yandex.com/maps/?text=<?= urlencode($address) ?>" target="_blank" rel="noopener noreferrer">
                    <svg class="icon"><use href="#i-pin"/></svg>
                    <span><?= __('contacts.map_open') ?></span>
                </a>
            </div>
        </div>

        <div class="requisites" id="requisites">
            <h2><?= Helpers::e(__('contacts.requisites')) ?></h2>
            <dl class="requisites__list">
                <div>
                    <dt><?= Helpers::e(__('contacts.req_full_name')) ?></dt>
                    <dd><?= Helpers::e(__('site.full_name')) ?></dd>
                </div>
                <div>
                    <dt><?= Helpers::e(__('contacts.address')) ?></dt>
                    <dd><?= Helpers::e($address) ?></dd>
                </div>
                <div>
                    <dt><?= Helpers::e(__('contacts.email')) ?></dt>
                    <dd><?= Helpers::e($email) ?></dd>
                </div>
                <div>
                    <dt><?= Helpers::e(__('contacts.registry')) ?></dt>
                    <dd><?= Helpers::e(Helpers::formatPhone($phoneRegistry)) ?></dd>
                </div>
            </dl>
        </div>
    </div>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
