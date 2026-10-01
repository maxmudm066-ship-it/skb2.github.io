<?php

/**
 * Общий подвал публичного сайта.
 */

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Forbidden');
}

$footerPhoneMain     = Settings::get('site_phone_main');
$footerPhoneRegistry = Settings::get('site_phone_registry');
$footerPhoneEmergency= Settings::get('site_phone_emergency');
$footerEmail         = Settings::get('site_email');
$footerAddress       = Settings::getL('site_address');
$footerHours         = Settings::getL('working_hours');
?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer__grid">
            <div class="footer__col footer__col--brand">
                <a class="brand brand--footer" href="<?= Helpers::url('index.php') ?>">
                    <span class="brand__logo" aria-hidden="true">
                        <svg class="icon"><use href="#i-cross"/></svg>
                    </span>
                    <span class="brand__text">
                        <strong><?= __('site.short') ?></strong>
                        <small><?= Helpers::e(__('site.subtitle')) ?></small>
                    </span>
                </a>
                <p class="footer__about"><?= Helpers::e(__('footer.about')) ?></p>
                <a class="footer__emergency" href="tel:<?= Helpers::e($footerPhoneEmergency) ?>">
                    <svg class="icon"><use href="#i-alert"/></svg>
                    <span><?= Helpers::e(__('footer.emergency')) ?></span>
                </a>
            </div>

            <nav class="footer__col" aria-label="<?= Helpers::e(__('footer.nav')) ?>">
                <h3><?= Helpers::e(__('footer.nav')) ?></h3>
                <ul>
                    <li><a href="<?= Helpers::url('index.php') ?>"><?= __('nav.home') ?></a></li>
                    <li><a href="<?= Helpers::url('doctors.php') ?>"><?= __('nav.doctors') ?></a></li>
                    <li><a href="<?= Helpers::url('news.php') ?>"><?= __('nav.news') ?></a></li>
                    <li><a href="<?= Helpers::url('kontakty.php') ?>"><?= __('nav.contacts') ?></a></li>
                    <li><a href="<?= Helpers::url('antikorupciya.php') ?>"><?= __('nav.anticorruption') ?></a></li>
                </ul>
            </nav>

            <nav class="footer__col" aria-label="<?= Helpers::e(__('footer.patients')) ?>">
                <h3><?= Helpers::e(__('footer.patients')) ?></h3>
                <ul>
                    <li><a href="<?= Helpers::url('zapis.php') ?>" class="footer__book"><?= __('nav.book') ?></a></li>
                    <li><a href="<?= Helpers::url('index.php#departments') ?>"><?= Helpers::e(__('footer.depts')) ?></a></li>
                    <li><a href="<?= Helpers::url('kontakty.php#requisites') ?>"><?= Helpers::e(__('contacts.requisites')) ?></a></li>
                    <li><a href="<?= Helpers::url('antikorupciya.php#laws') ?>"><?= Helpers::e(__('footer.docs')) ?></a></li>
                </ul>
            </nav>

            <div class="footer__col footer__col--contacts">
                <h3><?= Helpers::e(__('footer.contacts')) ?></h3>
                <ul class="footer__contacts-list">
                    <li>
                        <svg class="icon"><use href="#i-pin"/></svg>
                        <span><?= Helpers::e($footerAddress) ?></span>
                    </li>
                    <li>
                        <svg class="icon"><use href="#i-phone"/></svg>
                        <a href="tel:<?= Helpers::e(preg_replace('/\D+/', '', $footerPhoneRegistry)) ?>">
                            <?= Helpers::e(Helpers::formatPhone($footerPhoneRegistry)) ?>
                        </a>
                    </li>
                    <li>
                        <svg class="icon"><use href="#i-mail"/></svg>
                        <a href="mailto:<?= Helpers::e($footerEmail) ?>"><?= Helpers::e($footerEmail) ?></a>
                    </li>
                    <li>
                        <svg class="icon"><use href="#i-clock"/></svg>
                        <span><?= Helpers::e($footerHours) ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="footer__bottom">
            <p>&copy; <?= date('Y') ?> <?= Helpers::e(__('site.full_name')) ?>. <?= __('footer.rights') ?></p>
        </div>
    </div>
</footer>

<script src="<?= Helpers::url('assets/js/main.js') ?>"></script>
<?php if (!empty($pageJs)): ?>
<script src="<?= Helpers::url($pageJs) ?>"></script>
<?php endif; ?>
</body>
</html>
