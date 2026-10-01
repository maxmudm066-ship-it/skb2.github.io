<?php

/**
 * Карточка новости. Ожидает массив $item (строка таблицы news).
 */

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Forbidden');
}

$item = $item ?? null;
if (!$item) {
    return;
}

$newsTitle   = Lang::tx($item, 'title');
$newsExcerpt = Lang::tx($item, 'excerpt');
$newsImage   = (string)($item['image'] ?? '');
$newsDate    = $item['published_at'] ?? $item['created_at'] ?? null;
?>
<article class="news-card">
    <a class="news-card__img" href="<?= Helpers::url('news-item.php?id=' . (int)$item['id']) ?>">
        <?php if ($newsImage !== '' && is_file(UPLOAD_DIR . '/' . $newsImage)): ?>
            <img src="<?= Helpers::e(Helpers::url('uploads/' . $newsImage)) ?>" alt="<?= Helpers::e($newsTitle) ?>" loading="lazy" width="400" height="240">
        <?php else: ?>
            <span class="news-card__placeholder">
                <svg class="icon"><use href="#i-cross"/></svg>
            </span>
        <?php endif; ?>
    </a>
    <div class="news-card__body">
        <time class="news-card__date" datetime="<?= Helpers::e(substr((string)$newsDate, 0, 10)) ?>">
            <?= Helpers::e(Helpers::formatDate((string)$newsDate)) ?>
        </time>
        <h3 class="news-card__title">
            <a href="<?= Helpers::url('news-item.php?id=' . (int)$item['id']) ?>"><?= Helpers::e($newsTitle) ?></a>
        </h3>
        <?php if ($newsExcerpt !== ''): ?>
            <p class="news-card__excerpt"><?= Helpers::e($newsExcerpt) ?></p>
        <?php endif; ?>
        <a class="link-more" href="<?= Helpers::url('news-item.php?id=' . (int)$item['id']) ?>">
            <span><?= __('news.more') ?></span>
            <svg class="icon"><use href="#i-arrow"/></svg>
        </a>
    </div>
</article>
