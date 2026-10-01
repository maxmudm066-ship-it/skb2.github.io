<?php

/**
 * Общая шапка публичного сайта.
 * Ожидаемые переменные (определяются до include):
 *   $pageTitle  string  — заголовок вкладки (без имени сайта)
 *   $pageDesc   string  — meta description
 *   $pageCss    string  — (необязательно) путь к дополнительному CSS от корня
 *   $pageJs     string  — (необязательно) путь к дополнительному JS от корня
 *   $bodyClass  string  — (необязательно) класс <body>
 */

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Forbidden');
}

$pageTitle = $pageTitle ?? '';
$pageDesc  = $pageDesc ?? '';
$pageCss   = $pageCss ?? '';
$bodyClass = $bodyClass ?? '';

/** Ссылка на текущую страницу со сменой языка */
function langUrl(string $lang): string
{
    $query = $_GET;
    unset($query['lang']);
    $query['lang'] = $lang;
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    return $path . '?' . http_build_query($query);
}

$phoneMain     = Settings::get('site_phone_main');
$phoneRegistry = Settings::get('site_phone_registry');
$phoneEmergency= Settings::get('site_phone_emergency');
$address       = Settings::getL('site_address');
$workingHours  = Settings::getL('working_hours');

$currentLang = Lang::current();
$langNames   = ['ru' => 'Русский', 'uz' => "O'zbekcha", 'en' => 'English'];

$navItems = [
    ['url' => 'index.php#departments', 'label' => __('nav.departments')],
    ['url' => 'doctors.php',           'label' => __('nav.doctors')],
    ['url' => 'news.php',              'label' => __('nav.news')],
    ['url' => 'kontakty.php',          'label' => __('nav.contacts')],
    ['url' => 'antikorupciya.php',     'label' => __('nav.anticorruption')],
];
?>
<!DOCTYPE html>
<html lang="<?= Helpers::e($currentLang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= Helpers::e($pageDesc ?: __('site.subtitle')) ?>">
<meta name="theme-color" content="#0A2540">
<title><?= Helpers::e($pageTitle !== '' ? $pageTitle . ' — ' . __('site.short') : __('site.title')) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= Helpers::url('assets/img/favicon.svg') ?>">
<link rel="stylesheet" href="<?= Helpers::url('assets/css/main.css') ?>">
<?php if ($pageCss !== ''): ?>
<link rel="stylesheet" href="<?= Helpers::url($pageCss) ?>">
<?php endif; ?>
</head>
<body class="<?= Helpers::e($bodyClass) ?>">

<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
    <symbol id="i-phone" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></symbol>
    <symbol id="i-mail" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="22,6 12,13 2,6"/></symbol>
    <symbol id="i-pin" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></symbol>
    <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12,5 19,12 12,19"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><polyline points="6,9 12,15 18,9"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></symbol>
    <symbol id="i-close" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></symbol>
    <symbol id="i-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></symbol>
    <symbol id="i-doc" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></symbol>
    <symbol id="i-cross" viewBox="0 0 24 24"><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z"/></symbol>
    <symbol id="i-heart" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l8.84 8.84 8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/></symbol>
    <symbol id="i-pulse" viewBox="0 0 24 24"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></symbol>
    <symbol id="i-scan" viewBox="0 0 24 24"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><line x1="7" y1="12" x2="17" y2="12"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-smile" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></symbol>
    <symbol id="i-bone" viewBox="0 0 24 24"><g transform="rotate(45 12 12)" fill="currentColor" stroke="none"><rect x="10" y="5" width="4" height="14" rx="2"/><circle cx="9.6" cy="5" r="2.6"/><circle cx="14.4" cy="5" r="2.6"/><circle cx="9.6" cy="19" r="2.6"/><circle cx="14.4" cy="19" r="2.6"/></g></symbol>
    <symbol id="i-flask" viewBox="0 0 24 24"><path d="M9 3h6"/><path d="M10 3v5.5L4.8 17.8A2 2 0 0 0 6.6 21h10.8a2 2 0 0 0 1.8-3.2L14 8.5V3"/><line x1="7.5" y1="14.5" x2="16.5" y2="14.5"/></symbol>
    <symbol id="i-stetho" viewBox="0 0 24 24"><path d="M5 3v5a4 4 0 0 0 8 0V3"/><path d="M9 12v3a5 5 0 0 0 10 0v-2"/><circle cx="19" cy="10" r="2"/></symbol>
    <symbol id="i-stethoscope" viewBox="0 0 24 24"><path d="M5 3v5a4 4 0 0 0 8 0V3"/><path d="M9 12v3a5 5 0 0 0 10 0v-2"/><circle cx="19" cy="10" r="2"/></symbol>
    <symbol id="i-scalpel" viewBox="0 0 24 24"><path d="M2 22l6-1 12-12-5-5L3 16l-1 6z"/><line x1="14" y1="6" x2="18" y2="10"/></symbol>
    <symbol id="i-brain" viewBox="0 0 24 24"><path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 1.98-3A2.5 2.5 0 0 1 9.5 2z"/><path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-1.98-3A2.5 2.5 0 0 0 14.5 2z"/></symbol>
    <symbol id="i-mother" viewBox="0 0 24 24"><circle cx="8" cy="4" r="2"/><path d="M8 6v6"/><path d="M8 12l-2.5 9"/><path d="M8 12l2.5 9"/><circle cx="17" cy="8" r="1.5"/><path d="M17 9.5V14"/><path d="M17 14l-1.2 6"/><path d="M17 14l1.2 6"/></symbol>
    <symbol id="i-child" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 10h.01"/><path d="M15 10h.01"/><path d="M9.5 15c.7.6 1.5 1 2.5 1s1.8-.4 2.5-1"/></symbol>
</svg>

<a class="skip-link" href="#main"><?= __('common.skip') ?></a>

<header class="site-header">
    <div class="topbar">
        <div class="container topbar__inner">
            <div class="topbar__left">
                <a class="topbar__phone" href="tel:<?= Helpers::e(preg_replace('/\D+/', '', $phoneRegistry)) ?>">
                    <svg class="icon"><use href="#i-phone"/></svg>
                    <span><?= Helpers::e(Helpers::formatPhone($phoneRegistry)) ?></span>
                </a>
                <span class="topbar__hours">
                    <svg class="icon"><use href="#i-clock"/></svg>
                    <span><?= Helpers::e(__('topbar.registry')) ?></span>
                </span>
            </div>
            <div class="topbar__right">
                <a class="topbar__emergency" href="tel:<?= Helpers::e($phoneEmergency) ?>">
                    <svg class="icon"><use href="#i-alert"/></svg>
                    <span><?= __('topbar.emergency') ?> <strong><?= Helpers::e($phoneEmergency) ?></strong></span>
                </a>
                <div class="lang-switch" data-lang-switch>
                    <button class="lang-switch__btn" type="button" aria-haspopup="true" aria-expanded="false">
                        <svg class="icon"><use href="#i-globe"/></svg>
                        <span><?= Helpers::e(strtoupper($currentLang)) ?></span>
                        <svg class="icon icon--chev"><use href="#i-chevron"/></svg>
                    </button>
                    <ul class="lang-switch__menu">
                        <?php foreach ($langNames as $code => $name): ?>
                            <li>
                                <a href="<?= Helpers::e(langUrl($code)) ?>"
                                   hreflang="<?= Helpers::e($code) ?>"
                                   class="<?= $code === $currentLang ? 'is-current' : '' ?>">
                                    <?= Helpers::e($name) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="mainnav" data-mainnav>
        <div class="container mainnav__inner">
            <a class="brand" href="<?= Helpers::url('index.php') ?>">
                <span class="brand__logo" aria-hidden="true">
                    <svg class="icon"><use href="#i-cross"/></svg>
                </span>
                <span class="brand__text">
                    <strong><?= __('site.short') ?></strong>
                    <small><?= Helpers::e(__('site.subtitle')) ?></small>
                </span>
            </a>

            <nav class="mainnav__nav" id="site-nav" aria-label="<?= __('nav.menu') ?>">
                <ul>
                    <?php foreach ($navItems as $item): ?>
                        <li><a href="<?= Helpers::url($item['url']) ?>"><?= Helpers::e($item['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <a class="btn btn--accent mainnav__book" href="<?= Helpers::url('zapis.php') ?>">
                    <svg class="icon"><use href="#i-calendar"/></svg>
                    <span><?= __('nav.book') ?></span>
                </a>
            </nav>

            <a class="btn btn--accent btn--sm mainnav__book-desk" href="<?= Helpers::url('zapis.php') ?>">
                <svg class="icon"><use href="#i-calendar"/></svg>
                <span><?= __('nav.book') ?></span>
            </a>

            <button class="burger" type="button" aria-label="<?= __('nav.menu') ?>" aria-expanded="false" aria-controls="site-nav" data-burger>
                <svg class="icon icon--open"><use href="#i-menu"/></svg>
                <svg class="icon icon--close"><use href="#i-close"/></svg>
            </button>
        </div>
    </div>
</header>

<main id="main">
