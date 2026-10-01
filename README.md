# ЦКБ № 2 — сайт больницы с онлайн-записью

Официальный сайт Государственного учреждения «Центральная клиническая больница
№ 2 Главного медицинского управления при Администрации Президента Республики
Узбекистан»: публичная часть на трёх языках (RU / UZ / EN), мастер онлайн-записи
на приём и административная панель.

## Стек

- **PHP 8.2+** — чистый ООП без фреймворков, `declare(strict_types=1)`,
  PDO исключительно с подготовленными выражениями
- **MySQL 8.0+** — InnoDB, utf8mb4_unicode_ci
- **Frontend** — HTML5, CSS3 (переменные, Mobile-First, без фреймворков),
  Vanilla JS ES6+ (Fetch)
- **Веб-сервер** — Apache 2.4 (`.htaccess` в комплекте) или Nginx

## Требования к PHP

Расширения: `pdo_mysql`, `fileinfo` (проверка MIME загружаемых файлов),
`mbstring`, `openssl` (для `session.cookie_secure` и генерации токенов).
Опционально: модуль Argon2 (`PASSWORD_ARGON2ID`) — при его отсутствии пароли
хэшируются `PASSWORD_DEFAULT` (bcrypt), прозрачный rehash выполняется
автоматически при входе.

## Установка

### 1. База данных

```sql
CREATE DATABASE ckb2_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
CREATE USER 'ckb2_user'@'localhost' IDENTIFIED BY 'СТРОГИЙ_ПАРОЛЬ';
GRANT SELECT, INSERT, UPDATE, DELETE ON ckb2_db.* TO 'ckb2_user'@'localhost';
FLUSH PRIVILEGES;
```

Импорт схемы с демо-данными:

```bash
mysql -u root -p ckb2_db < schema.sql
```

`schema.sql` создаёт таблицы, справочники (10 отделений, 12 врачей,
расписания, новости) и двух пользователей админки. При повторном импорте
все таблицы пересоздаются (`DROP TABLE IF EXISTS`).

### 2. Конфигурация

Отредактируйте `config/database.php`:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'ckb2_db');
define('DB_USER', 'ckb2_user');
define('DB_PASS', 'СТРОГИЙ_ПАРОЛЬ');
```

При необходимости в `config/config.php`:

- `APP_ENV` — `development` включает вывод ошибок на экран
  (в production ошибки пишутся в `storage/logs/php-error.log`);
- `BOOKING_MAX_DAYS` — горизонт записи (по умолчанию 30 дней);
- `BOOKING_LEAD_MINUTES` — минимальное время до приёма в день записи;
- `RATE_*` — лимиты частоты запросов.

### 3. Права на каталоги

Каталоги `uploads/`, `uploads/doctors/`, `uploads/news/` и
`storage/logs/` должны быть доступны веб-серверу для записи:

```bash
chown -R www-data:www-data uploads storage
chmod -R 755 uploads storage
```

### 4. Веб-сервер

**Apache 2.4.** DocumentRoot — на каталог проекта. Требуются модули
`mod_rewrite`, `mod_headers`, `mod_expires` (последние два опциональны).
Конфигурация уже полностью в корневом `.htaccess`: ЧПВ, заголовки
безопасности, запрет листинга, служебные каталоги закрыты.

**Nginx** (аналог корневого `.htaccess`):

```nginx
server {
    listen 80;
    server_name ckb2.uz;
    root /var/www/ckb2;
    index index.php;

    charset utf-8;

    # Заголовки безопасности (аналог mod_headers)
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'" always;

    # Служебные каталоги и файлы — 404
    location ~ ^/(config|includes|classes|storage)/ { return 404; }
    location ~* \.(sql|md|log|ini|env)$             { return 404; }

    # uploads: скрипты запрещены, только изображения
    location ~* ^/uploads/.*\.(php|pht|phtml|phar|pl|py|cgi|sh)$ { return 403; }

    # ЧПВ: /zapis -> /zapis.php
    location / {
        try_files $uri $uri/ @extensionless;
    }
    location @extensionless {
        rewrite ^(.+)$ $1.php last;
    }
    location = /contacts       { rewrite ^ /kontakty.php last; }
    location = /anticorruption { rewrite ^ /antikorupciya.php last; }

    error_page 404 /404.php;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~* \.(css|js)$      { expires 7d;  }
    location ~* \.(jpg|png|webp|svg|ico)$ { expires 30d; }
}
```

### 5. Учётные данные админки

После импорта `schema.sql` (смените пароли при первом входе):

| Логин       | Пароль         | Роль                  |
|-------------|----------------|-----------------------|
| `admin`     | `Admin@2026!`  | Администратор         |
| `registratura` | `Operator@2026!` | Оператор регистратуры |

Админ-панель: `/admin/`. Оператору доступны только дашборд, записи на приём
и расписания; разделы врачей, отделений, новостей, настроек и пользователей —
только администратору. Сессия завершается автоматически после 30 минут
простоя.

## Структура проекта

```
├── index.php            Главная
├── doctors.php          Врачи (фильтр по отделениям)
├── news.php, news-item.php   Новости и страница новости
├── kontakty.php         Контакты и реквизиты
├── antikorupciya.php    Антикоррупционная политика
├── zapis.php            Онлайн-запись (5 шагов)
├── 404.php              Страница ошибки
├── api/                 JSON API мастера записи
│   ├── get-doctors.php        врачи выбранного отделения
│   ├── get-availability.php   даты со свободными слотами
│   ├── get-slots.php          слоты выбранного дня
│   └── book-appointment.php   создание заявки
├── admin/               Административная панель
│   ├── login.php, logout.php, index.php (дашборд)
│   ├── appointments.php + api/status.php   записи, смена статуса, экспорт CSV
│   ├── schedules.php    недельные графики, блокировка дат и слотов
│   ├── doctors.php, doctor-form.php
│   ├── departments.php  отделения
│   ├── news.php, news-form.php
│   ├── settings.php     контакты, адрес, режим работы
│   ├── users.php, user-form.php
│   └── partials/        шапка, сайдбар, подвал
├── classes/             Ядро: Database, Auth, Csrf, RateLimiter, Upload,
│                        Appointment, ScheduleService, Settings, Lang, Helpers
├── config/              config.php (константы), database.php (подключение)
├── includes/            bootstrap.php, header/footer, локализация lang/{ru,uz,en}
├── assets/              css/ (main, booking, admin), js/ (main, booking, admin)
├── uploads/             doctors/, news/ — только изображения (.htaccess)
├── storage/logs/        Журнал ошибок PHP (закрыт от веба)
├── schema.sql           Схема БД и демонстрационные данные
└── .htaccess            ЧПВ, заголовки безопасности, запреты
```

## Модель безопасности

- **SQL-инъекции** — только подготовленные выражения PDO
  (`PDO::ATTR_EMULATE_PREPARES => false`).
- **Пароли** — `password_hash()` (Argon2id / bcrypt) + прозрачный rehash.
- **Сессии** — HttpOnly, Secure при HTTPS, SameSite=Strict,
  `session_regenerate_id(true)` при входе, автовыход после простоя.
- **CSRF** — токен в сессии, проверка каждого POST-запроса и AJAX-вызова
  (поле `csrf_token` или заголовок `X-CSRF-Token`, сравнение `hash_equals`).
- **XSS** — весь вывод через `htmlspecialchars()`; JSON-ответы API отдаются
  с `X-Content-Type-Options: nosniff`.
- **Валидация** — телефоны строго `+998XXXXXXXXX`, паспорт `AA1234567`,
  ПИНФЛ 14 цифр, даты — календарно корректные и в допустимом горизонте.
- **Anti-spam** — Honeypot-поле + rate limiting: не более 3 заявок за 10 минут
  и не более 5 попыток входа за 15 минут с одного IP.
- **Загрузки** — проверка расширения, реального MIME (finfo) и целостности
  (getimagesize), случайные имена файлов, запрет исполнения скриптов в
  `uploads/` через `.htaccess`.
- **Конкуренция за слот** — генерируемая колонка `active_slot_time` c
  UNIQUE-индексом: два пациента не могут занять одно время у одного врача.

## Экспорт записей

Раздел «Записи на приём» → «Экспорт CSV» (кодировка UTF-8 с BOM, разделитель
`;` — корректно открывается в русской локали Excel) и «Печать» (форматированная
печатная форма для регистратуры).
