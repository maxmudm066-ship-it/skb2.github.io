<?php
/**
 * Новость: создание и редактирование.
 * Поля заполняются на трёх языках; русский — обязательный.
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

Auth::requireAdmin();

$db = Database::get();
$id = (int)($_GET['id'] ?? 0);

/* ---- Загрузка текущих данных ---- */
$news = [
    'id' => 0,
    'title_ru' => '', 'title_uz' => '', 'title_en' => '',
    'excerpt_ru' => '', 'excerpt_uz' => '', 'excerpt_en' => '',
    'body_ru' => '', 'body_uz' => '', 'body_en' => '',
    'image' => '', 'is_published' => 1, 'published_at' => '',
];
if ($id > 0) {
    $st = $db->prepare("SELECT * FROM news WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        Helpers::flashSet('error', 'Новость не найдена.');
        Helpers::redirect(Helpers::url('admin/news.php'));
    }
    $news = array_merge($news, $row);
    $news['published_at'] = $row['published_at']
        ? date('Y-m-d\TH:i', strtotime((string)$row['published_at']))
        : '';
}

/* ---- Сохранение ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verifyRequest()) {
        Helpers::flashSet('error', 'Недействительный CSRF-токен. Повторите действие.');
        Helpers::redirect(Helpers::url($id > 0 ? 'admin/news-form.php?id=' . $id : 'admin/news-form.php'));
    }

    $in = $_POST;
    $errors = [];

    $titleRu = trim((string)($in['title_ru'] ?? ''));
    $titleUz = trim((string)($in['title_uz'] ?? ''));
    $titleEn = trim((string)($in['title_en'] ?? ''));
    $excerptRu = trim((string)($in['excerpt_ru'] ?? ''));
    $excerptUz = trim((string)($in['excerpt_uz'] ?? ''));
    $excerptEn = trim((string)($in['excerpt_en'] ?? ''));
    $bodyRu = trim((string)($in['body_ru'] ?? ''));
    $bodyUz = trim((string)($in['body_uz'] ?? ''));
    $bodyEn = trim((string)($in['body_en'] ?? ''));
    $publishedAtRaw = trim((string)($in['published_at'] ?? ''));
    $isPublished = isset($in['is_published']) ? 1 : 0;

    if (mb_strlen($titleRu) < 3 || mb_strlen($titleRu) > 255) {
        $errors[] = 'Заголовок (рус.) — обязательно, от 3 до 255 символов.';
    }
    foreach (['uz' => $titleUz, 'en' => $titleEn] as $lang => $val) {
        if (mb_strlen($val) > 255) {
            $errors[] = 'Заголовок (' . $lang . '.) — не более 255 символов.';
        }
    }
    foreach (['рус.' => $excerptRu, 'узб.' => $excerptUz, 'англ.' => $excerptEn] as $lang => $val) {
        if (mb_strlen($val) > 500) {
            $errors[] = 'Краткое описание (' . $lang . ') — не более 500 символов.';
        }
    }
    foreach (['рус.' => $bodyRu, 'узб.' => $bodyUz, 'англ.' => $bodyEn] as $lang => $val) {
        if (mb_strlen($val) > 65535) {
            $errors[] = 'Текст новости (' . $lang . ') слишком длинный.';
        }
    }

    /* дата публикации: пусто → NOW() при создании, прежняя при редактировании */
    $publishedAt = null;
    if ($publishedAtRaw !== '') {
        $ts = strtotime($publishedAtRaw);
        if ($ts === false) {
            $errors[] = 'Некорректная дата публикации.';
        } else {
            $publishedAt = date('Y-m-d H:i:s', $ts);
        }
    } elseif ($id > 0) {
        $publishedAt = $news['published_at'] !== ''
            ? date('Y-m-d H:i:s', strtotime($news['published_at']))
            : null;
    }

    /* ---- Загрузка изображения ---- */
    $image = $news['image'];
    $newImage = null;
    if (isset($_FILES['image']) && (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $newImage = Upload::image($_FILES['image'], 'news', $imageError);
        if ($newImage === null && $imageError !== null) {
            $errors[] = $imageError;
        }
    }

    if ($errors === []) {
        try {
            if ($id > 0) {
                $st = $db->prepare(
                    "UPDATE news SET title_ru=?, title_uz=?, title_en=?,
                         excerpt_ru=?, excerpt_uz=?, excerpt_en=?,
                         body_ru=?, body_uz=?, body_en=?,
                         image=?, is_published=?, published_at=?
                     WHERE id=?"
                );
                $st->execute([
                    $titleRu, $titleUz, $titleEn,
                    $excerptRu, $excerptUz, $excerptEn,
                    $bodyRu, $bodyUz, $bodyEn,
                    $newImage ?? $image, $isPublished, $publishedAt, $id,
                ]);
            } else {
                $st = $db->prepare(
                    "INSERT INTO news (title_ru, title_uz, title_en,
                         excerpt_ru, excerpt_uz, excerpt_en,
                         body_ru, body_uz, body_en,
                         image, is_published, published_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
                );
                $st->execute([
                    $titleRu, $titleUz, $titleEn,
                    $excerptRu, $excerptUz, $excerptEn,
                    $bodyRu, $bodyUz, $bodyEn,
                    $newImage ?? $image, $isPublished,
                    $publishedAt ?? date('Y-m-d H:i:s'),
                ]);
                $id = (int)$db->lastInsertId();
            }

            /* старое изображение удаляем только после успешного сохранения */
            if ($newImage !== null && $news['image'] && $news['image'] !== $newImage) {
                Upload::delete((string)$news['image']);
            }

            Helpers::flashSet('success', 'Новость сохранена.');
            Helpers::redirect(Helpers::url('admin/news.php'));
        } catch (PDOException $e) {
            if ($newImage !== null) {
                Upload::delete($newImage);
            }
            throw $e;
        }
    }

    /* при ошибках — вернуть введённые значения в форму */
    $news = array_merge($news, [
        'title_ru' => $titleRu, 'title_uz' => $titleUz, 'title_en' => $titleEn,
        'excerpt_ru' => $excerptRu, 'excerpt_uz' => $excerptUz, 'excerpt_en' => $excerptEn,
        'body_ru' => $bodyRu, 'body_uz' => $bodyUz, 'body_en' => $bodyEn,
        'is_published' => $isPublished,
        'published_at' => $publishedAt !== null ? date('Y-m-d\TH:i', strtotime($publishedAt)) : '',
        'image' => $newImage ?? $image,
    ]);
    $formErrors = $errors;
}

$pageTitle = $id > 0 ? 'Новость: ' . $news['title_ru'] : 'Новая новость';
$activeNav = 'news';
require APP_ROOT . '/admin/partials/header.php';
?>

<div class="page-head">
    <h2><?= $id > 0 ? 'Редактирование новости' : 'Новая новость' ?></h2>
    <div class="page-head__spacer"></div>
    <div class="page-head__actions">
        <a class="btn btn--ghost btn--sm" href="<?= Helpers::e(Helpers::url('admin/news.php')) ?>">
            &larr; К списку новостей
        </a>
    </div>
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

<div class="card">
    <div class="card__body">
        <form method="post" action="<?= Helpers::e(Helpers::url('admin/news-form.php' . ($id > 0 ? '?id=' . $id : ''))) ?>"
              enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">

            <div class="form-grid">
                <div class="field">
                    <label class="field__label" for="f-title-ru">Заголовок (рус.) <span class="req">*</span></label>
                    <input class="field__input" type="text" id="f-title-ru" name="title_ru" maxlength="255"
                           required value="<?= Helpers::e($news['title_ru']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-title-uz">Заголовок (узб.)</label>
                    <input class="field__input" type="text" id="f-title-uz" name="title_uz" maxlength="255"
                           value="<?= Helpers::e($news['title_uz']) ?>">
                </div>
                <div class="field">
                    <label class="field__label" for="f-title-en">Заголовок (англ.)</label>
                    <input class="field__input" type="text" id="f-title-en" name="title_en" maxlength="255"
                           value="<?= Helpers::e($news['title_en']) ?>">
                </div>
            </div>

            <div class="form-grid" style="margin-top:16px">
                <div class="field">
                    <label class="field__label" for="f-ex-ru">Краткое описание (рус.)</label>
                    <textarea class="field__textarea" id="f-ex-ru" name="excerpt_ru" rows="2"
                              maxlength="500"><?= Helpers::e($news['excerpt_ru']) ?></textarea>
                </div>
                <div class="field">
                    <label class="field__label" for="f-ex-uz">Краткое описание (узб.)</label>
                    <textarea class="field__textarea" id="f-ex-uz" name="excerpt_uz" rows="2"
                              maxlength="500"><?= Helpers::e($news['excerpt_uz']) ?></textarea>
                </div>
                <div class="field">
                    <label class="field__label" for="f-ex-en">Краткое описание (англ.)</label>
                    <textarea class="field__textarea" id="f-ex-en" name="excerpt_en" rows="2"
                              maxlength="500"><?= Helpers::e($news['excerpt_en']) ?></textarea>
                </div>
            </div>

            <div class="field" style="margin-top:16px">
                <label class="field__label" for="f-body-ru">Текст новости (рус.)</label>
                <textarea class="field__textarea" id="f-body-ru" name="body_ru" rows="8"><?= Helpers::e($news['body_ru']) ?></textarea>
            </div>
            <div class="form-grid" style="margin-top:16px">
                <div class="field">
                    <label class="field__label" for="f-body-uz">Текст новости (узб.)</label>
                    <textarea class="field__textarea" id="f-body-uz" name="body_uz" rows="6"><?= Helpers::e($news['body_uz']) ?></textarea>
                </div>
                <div class="field">
                    <label class="field__label" for="f-body-en">Текст новости (англ.)</label>
                    <textarea class="field__textarea" id="f-body-en" name="body_en" rows="6"><?= Helpers::e($news['body_en']) ?></textarea>
                </div>
            </div>

            <div class="form-grid" style="margin-top:16px">
                <div class="field">
                    <label class="field__label" for="f-image">Изображение <small>(JPG, PNG, WEBP, до 4 МБ)</small></label>
                    <input class="field__input" type="file" id="f-image" name="image"
                           accept="image/jpeg,image/png,image/webp">
                    <p class="field__hint">
                        Горизонтальное изображение 16:9 смотрится лучше всего.
                    </p>
                </div>
                <div class="field">
                    <label class="field__label" for="f-pub-at">Дата и время публикации</label>
                    <input class="field__input" type="datetime-local" id="f-pub-at" name="published_at"
                           value="<?= Helpers::e($news['published_at']) ?>">
                    <p class="field__hint">Оставьте пустым — будет установлено текущее время.</p>
                </div>
                <?php if ($news['image']): ?>
                    <div class="field">
                        <span class="field__label">Текущее изображение</span>
                        <img class="thumb thumb--wide" src="<?= Helpers::e(Helpers::url('uploads/' . $news['image'])) ?>"
                             alt="<?= Helpers::e($news['title_ru']) ?>" style="height:120px">
                    </div>
                <?php endif; ?>
            </div>

            <label class="check" style="margin-top:16px">
                <input type="checkbox" name="is_published" <?= $news['is_published'] ? 'checked' : '' ?>>
                Опубликовать новость на сайте
            </label>

            <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap">
                <button class="btn btn--accent" type="submit">Сохранить</button>
                <a class="btn btn--ghost" href="<?= Helpers::e(Helpers::url('admin/news.php')) ?>">Отмена</a>
            </div>
        </form>
    </div>
</div>

<?php require APP_ROOT . '/admin/partials/footer.php'; ?>
