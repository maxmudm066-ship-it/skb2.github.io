<?php

/**
 * Антикоррупционная политика больницы.
 */

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = __('ac.page_title');
$pageDesc  = __('ac.page_lead');

$phoneMain = Settings::get('site_phone_main');
$email     = Settings::get('site_email');

require APP_ROOT . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1><?= Helpers::e(__('ac.page_title')) ?></h1>
        <p><?= Helpers::e(__('ac.page_lead')) ?></p>
    </div>
</section>

<section class="section">
    <div class="container container--narrow">
        <div class="prose">
            <p class="prose__intro"><?= Helpers::e(__('ac.intro')) ?></p>

            <h2><?= Helpers::e(__('ac.principles_title')) ?></h2>
            <ul class="check-list">
                <li><svg class="icon"><use href="#i-check"/></svg><span><?= Helpers::e(__('ac.p1')) ?></span></li>
                <li><svg class="icon"><use href="#i-check"/></svg><span><?= Helpers::e(__('ac.p2')) ?></span></li>
                <li><svg class="icon"><use href="#i-check"/></svg><span><?= Helpers::e(__('ac.p3')) ?></span></li>
                <li><svg class="icon"><use href="#i-check"/></svg><span><?= Helpers::e(__('ac.p4')) ?></span></li>
            </ul>

            <h2 id="laws"><?= Helpers::e(__('ac.laws_title')) ?></h2>
            <ul class="docs-list">
                <li><svg class="icon"><use href="#i-doc"/></svg><span><?= Helpers::e(__('ac.l1')) ?></span></li>
                <li><svg class="icon"><use href="#i-doc"/></svg><span><?= Helpers::e(__('ac.l2')) ?></span></li>
                <li><svg class="icon"><use href="#i-doc"/></svg><span><?= Helpers::e(__('ac.l3')) ?></span></li>
            </ul>
        </div>

        <div class="hotline">
            <span class="hotline__icon"><svg class="icon"><use href="#i-phone"/></svg></span>
            <div class="hotline__body">
                <h2><?= Helpers::e(__('ac.hotline_title')) ?></h2>
                <p><?= Helpers::e(__('ac.hotline_text')) ?></p>
                <div class="hotline__contacts">
                    <a href="tel:<?= Helpers::e(preg_replace('/\D+/', '', $phoneMain)) ?>">
                        <svg class="icon"><use href="#i-phone"/></svg>
                        <span><?= Helpers::e(Helpers::formatPhone($phoneMain)) ?></span>
                    </a>
                    <a href="mailto:<?= Helpers::e($email) ?>">
                        <svg class="icon"><use href="#i-mail"/></svg>
                        <span><?= Helpers::e($email) ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
