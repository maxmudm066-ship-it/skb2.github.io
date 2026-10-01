<?php
/**
 * Управление новостями: список, публикация/снятие с публикации, удаление.
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

Auth::requireAdmin();

$db = Database::get();

/* ---- POST-действия ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verifyRequest()) {
        Helpers::flashSet('error', 'Недействительный CSRF-токен. Повторите действие.');
        Helpers::redirect(Helpers::url('admin/news.php'));
    }

    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if ($id > 0 && $action === 'toggle') {
        $st = $db->prepare("UPDATE news SET is_published = 1 - is_published WHERE id = ?");
        $st->execute([$id]);
        Helpers::flashSet('success', 'Статус публикации новости изменён.');
    } elseif ($id > 0 && $action === 'delete') {
        $st = $db->prepare("SELECT image FROM news WHERE id = ?");
        $st->execute([$id]);
        $image = $st->fetchColumn();
        $st = $db->prepare("DELETE FROM news WHERE id = ?");
        $st->execute([$id]);
        if ($image) {
            Upload::delete((string)$image);
        }
        Helpers::flashSet('success', 'Новость удалена.');
    }
    Helpers::redirect(Helpers::url('admin/news.php'));
}

/* ---- Список ---- */
$news = [];
$st = $db->query(
    "SELECT id, title_ru, excerpt_ru, image, is_published, published_at, created_at
     FROM news
     ORDER BY COALESCE(published_at, created_at) DESC, id DESC
     LIMIT 200"
);
while ($r = $st->fetch()) {
    $news[] = $r;
}

$pageTitle = 'Новости';
$activeNav = 'news';
require APP_ROOT . '/admin/partials/header.php';
?>

<div class="page-head">
    <h2>Новости <span class="table__muted">(<?= count($news) ?>)</span></h2>
    <div class="page-head__spacer"></div>
    <div class="page-head__actions">
        <a class="btn btn--accent btn--sm" href="<?= Helpers::e(Helpers::url('admin/news-form.php')) ?>">
            <svg class="icon"><use href="#a-plus"/></svg>
            Добавить новость
        </a>
    </div>
</div>

<div class="card">
    <?php if ($news === []): ?>
        <div class="empty">Новостей пока нет.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th></th>
                    <th>Заголовок</th>
                    <th>Дата публикации</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($news as $n): ?>
                    <tr>
                        <td>
                            <?php if ($n['image']): ?>
                                <img class="thumb thumb--wide" src="<?= Helpers::e(Helpers::url('uploads/' . $n['image'])) ?>"
                                     alt="<?= Helpers::e($n['title_ru']) ?>">
                            <?php else: ?>
                                <span class="thumb-empty thumb--wide">
                                    <svg class="icon"><use href="#a-doc"/></svg>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= Helpers::e($n['title_ru']) ?>
                            <?php if ($n['excerpt_ru']): ?>
                                <div class="table__muted"><?= Helpers::e(mb_strimwidth($n['excerpt_ru'], 0, 120, '…')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="table__muted">
                            <?= $n['published_at']
                                ? Helpers::e(date('d.m.Y H:i', strtotime((string)$n['published_at'])))
                                : '—' ?>
                        </td>
                        <td>
                            <span class="badge badge--<?= $n['is_published'] ? 'confirmed' : 'muted' ?>">
                                <?= $n['is_published'] ? 'Опубликовано' : 'Черновик' ?>
                            </span>
                        </td>
                        <td>
                            <div class="table__actions">
                                <a class="btn btn--outline btn--sm" href="<?= Helpers::e(Helpers::url('admin/news-form.php?id=' . (int)$n['id'])) ?>">
                                    <svg class="icon"><use href="#a-edit"/></svg>
                                    Изменить
                                </a>
                                <form method="post" action="<?= Helpers::e(Helpers::url('admin/news.php')) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <button class="btn btn--ghost btn--sm" type="submit">
                                        <?= $n['is_published'] ? 'Снять' : 'Опубликовать' ?>
                                    </button>
                                </form>
                                <form method="post" action="<?= Helpers::e(Helpers::url('admin/news.php')) ?>"
                                      data-confirm="Удалить новость «<?= Helpers::e($n['title_ru']) ?>»? Отменить действие будет невозможно.">
                                    <input type="hidden" name="csrf_token" value="<?= Helpers::e(Csrf::token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="btn btn--danger btn--sm" type="submit">
                                        <svg class="icon"><use href="#a-trash"/></svg>
                                        Удалить
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require APP_ROOT . '/admin/partials/footer.php'; ?>
