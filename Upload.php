<?php
/**
 * Безопасная загрузка изображений (фото врачей, новости).
 * Проверки: размер, расширение, реальный MIME-тип (finfo), getimagesize.
 * Файл сохраняется под случайным именем; каталог /uploads/ закрыт
 * от исполнения скриптов через .htaccess.
 */

declare(strict_types=1);

if (!defined('APP_RUNNING')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Upload
{
    private const ALLOWED = [
        'jpg'  => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
    ];

    /**
     * Загружает изображение в подкаталог UPLOAD_DIR/$subdir.
     * Возвращает относительный путь (например "doctors/66f2a8b9c1d2e.jpg")
     * или null при неудаче. Описание причины — в $error.
     */
    public static function image(array $file, string $subdir, ?string &$error = null): ?string
    {
        $error = null;

        if (!isset($file['error']) || is_array($file['error'])) {
            $error = 'Некорректный запрос загрузки.';
            return null;
        }
        if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // файл не выбран — не ошибка
        }
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Ошибка загрузки файла (код ' . (int)$file['error'] . ').';
            return null;
        }
        if ((int)$file['size'] > MAX_UPLOAD_SIZE) {
            $error = 'Файл превышает допустимый размер 4 МБ.';
            return null;
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            $error = 'Файл не был загружен через HTTP POST.';
            return null;
        }

        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            $error = 'Допустимые форматы: JPG, PNG, WEBP.';
            return null;
        }

        // реальный MIME-тип по содержимому файла
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED[$ext], true)) {
            $error = 'Содержимое файла не соответствует изображению.';
            return null;
        }

        // дополнительная проверка целостности изображения
        $info = @getimagesize($file['tmp_name']);
        if ($info === false || empty($info[0]) || empty($info[1])) {
            $error = 'Файл не является корректным изображением.';
            return null;
        }

        $dir = realpath(UPLOAD_DIR) . '/' . preg_replace('/[^a-z0-9_-]+/', '', strtolower($subdir));
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $error = 'Не удалось создать каталог загрузки.';
            return null;
        }

        $name = uniqid('', true) . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $error = 'Не удалось сохранить файл на сервере.';
            return null;
        }
        @chmod($dest, 0644);

        return $subdir === '' ? $name : $subdir . '/' . $name;
    }

    /** Удаляет файл внутри UPLOAD_DIR с защитой от path traversal. */
    public static function delete(?string $relPath): void
    {
        $relPath = trim((string)$relPath);
        if ($relPath === '') {
            return;
        }
        $base = realpath(UPLOAD_DIR);
        $full = realpath(UPLOAD_DIR . '/' . $relPath);
        if ($base && $full && str_starts_with($full, $base . DIRECTORY_SEPARATOR) && is_file($full)) {
            @unlink($full);
        }
    }
}
