<?php

/**
 * Страница ошибки 404.
 */

if (!defined('APP_RUNNING')) {
    require __DIR__ . '/includes/bootstrap.php';
}

http_response_code(404);

$pageTitle = __('p404.title');
$pageDesc  = __('p404.text');

require APP_ROOT . '/includes/header.php';
?>

<section class="section section--grow">
    <div class="container">
        <div class="error-page">
            <p class="error-page__code">404</p>
            <h1><?= Helpers::e(__('p404.title')) ?></h1>
            <p><?= Helpers::e(__('p404.text')) ?></p>
            <div class="hero__cta">
                <a class="btn btn--primary" href="<?= Helpers::url('index.php') ?>">
                    <svg class="icon"><use href="#i-arrow"/></svg>
                    <span><?= __('p404.home') ?></span>
                </a>
                <a class="btn btn--outline" href="<?= Helpers::url('zapis.php') ?>">
                    <svg class="icon"><use href="#i-calendar"/></svg>
                    <span><?= __('nav.book') ?></span>
                </a>
            </div>
        </div>
    </div>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
