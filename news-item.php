<?php

/**
 * Страница отдельной новости: /news-item.php?id=N
 */

require __DIR__ . '/includes/bootstrap.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$item = null;
if ($id !== false && $id !== null) {
    $st = Database::get()->prepare(
        "SELECT * FROM news WHERE id = ? AND is_published = 1 LIMIT 1"
    );
    $st->execute([$id]);
    $item = $st->fetch();
}

if ($item === null) {
    http_response_code(404);
    require APP_ROOT . '/404.php';
    exit;
}

$newsTitle = Lang::tx($item, 'title');
$pageTitle = $newsTitle;
$pageDesc  = mb_substr(Lang::tx($item, 'excerpt'), 0, 200);

/* Соседние новости (для блока «читайте также») */
$related = Database::get()->prepare(
    "SELECT * FROM news
     WHERE is_published = 1 AND id <> ?
     ORDER BY COALESCE(published_at, created_at) DESC
     LIMIT 2"
);
$related->execute([$id]);
$related = $related->fetchAll();

$newsBody   = Lang::tx($item, 'body');
$newsImage  = (string)($item['image'] ?? '');
$newsDate   = $item['published_at'] ?? $item['created_at'] ?? null;

require APP_ROOT . '/includes/header.php';
?>

<article class="news-item">
    <section class="page-hero">
        <div class="container">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="<?= Helpers::url('index.php') ?>"><?= __('nav.home') ?></a>
                <span>/</span>
                <a href="<?= Helpers::url('news.php') ?>"><?= __('nav.news') ?></a>
            </nav>
            <h1><?= Helpers::e($newsTitle) ?></h1>
            <time class="page-hero__date" datetime="<?= Helpers::e(substr((string)$newsDate, 0, 10)) ?>">
                <?= Helpers::e(Helpers::formatDate((string)$newsDate)) ?>
            </time>
        </div>
    </section>

    <section class="section">
        <div class="container container--narrow">
            <?php if ($newsImage !== '' && is_file(UPLOAD_DIR . '/' . $newsImage)): ?>
                <figure class="news-item__figure">
                    <img src="<?= Helpers::e(Helpers::url('uploads/' . $newsImage)) ?>"
                         alt="<?= Helpers::e($newsTitle) ?>" width="1200" height="600">
                </figure>
            <?php endif; ?>

            <div class="news-item__body">
                <?php foreach (preg_split('/\n{2,}/u', $newsBody) ?: [] as $paragraph): ?>
                    <?php if (trim($paragraph) !== ''): ?>
                        <p><?= nl2br(Helpers::e(trim($paragraph))) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="news-item__back">
                <a class="btn btn--outline" href="<?= Helpers::url('news.php') ?>">
                    <svg class="icon icon--flip"><use href="#i-arrow"/></svg>
                    <span><?= __('news.back') ?></span>
                </a>
            </div>
        </div>
    </section>
</article>

<?php if ($related !== []): ?>
<section class="section section--muted">
    <div class="container">
        <div class="news-grid news-grid--page">
            <?php foreach ($related as $item): ?>
                <?php require APP_ROOT . '/includes/partials/news-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require APP_ROOT . '/includes/footer.php'; ?>
