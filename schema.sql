-- ============================================================================
--  ЦКБ № 2 — Центральная клиническая больница № 2 Главного медицинского
--  управления при Администрации Президента Республики Узбекистан
--  Схема базы данных: ckb2_db  |  MySQL 8.0+  |  InnoDB  |  utf8mb4
--
--  Импорт:  mysql -u root -p < schema.sql
--  Учётные данные по умолчанию (сменить после установки!):
--    admin      / Admin@2026!      (администратор)
--    registratura / Operator@2026! (оператор регистратуры)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `ckb2_db`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `ckb2_db`;

-- ----------------------------------------------------------------------------
-- Таблица: users — сотрудники (администраторы и операторы регистратуры)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)      NOT NULL,
  `full_name`     VARCHAR(150)     NOT NULL,
  `email`         VARCHAR(100)     DEFAULT NULL,
  `password_hash` VARCHAR(255)     NOT NULL,
  `role`          ENUM('admin','operator') NOT NULL DEFAULT 'operator',
  `is_active`     TINYINT(1)       NOT NULL DEFAULT 1,
  `last_login`    DATETIME         DEFAULT NULL,
  `created_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Таблица: departments — отделения (направления) клиники
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name_ru`        VARCHAR(200)  NOT NULL,
  `name_uz`        VARCHAR(200)  DEFAULT NULL,
  `name_en`        VARCHAR(200)  DEFAULT NULL,
  `slug`           VARCHAR(100)  NOT NULL,
  `description_ru` TEXT          DEFAULT NULL,
  `description_uz` TEXT          DEFAULT NULL,
  `description_en` TEXT          DEFAULT NULL,
  `icon`           VARCHAR(50)   DEFAULT NULL,
  `sort_order`     INT           NOT NULL DEFAULT 0,
  `is_active`      TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_departments_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Таблица: doctors — врачи, привязанные к отделениям
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `doctors`;
CREATE TABLE `doctors` (
  `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `department_id`    INT UNSIGNED   NOT NULL,
  `name_ru`          VARCHAR(200)   NOT NULL,
  `name_uz`          VARCHAR(200)   DEFAULT NULL,
  `name_en`          VARCHAR(200)   DEFAULT NULL,
  `position_ru`      VARCHAR(200)   NOT NULL,
  `position_uz`      VARCHAR(200)   DEFAULT NULL,
  `position_en`      VARCHAR(200)   DEFAULT NULL,
  `category`         VARCHAR(100)   DEFAULT NULL,
  `experience_years` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `office`           VARCHAR(20)    DEFAULT NULL,
  `photo`            VARCHAR(255)   DEFAULT NULL,
  `bio`              TEXT           DEFAULT NULL,
  `slot_duration`    SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `is_active`        TINYINT(1)     NOT NULL DEFAULT 1,
  `sort_order`       INT            NOT NULL DEFAULT 0,
  `created_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doctors_department` (`department_id`),
  CONSTRAINT `fk_doctors_department` FOREIGN KEY (`department_id`)
    REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Таблица: doctor_schedules — недельные графики приёма врачей
--   weekday: 1 = понедельник ... 7 = воскресенье (ISO-8601)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `doctor_schedules`;
CREATE TABLE `doctor_schedules` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id`  INT UNSIGNED NOT NULL,
  `weekday`    TINYINT      NOT NULL,
  `start_time` TIME         NOT NULL,
  `end_time`   TIME         NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_row` (`doctor_id`, `weekday`, `start_time`, `end_time`),
  KEY `idx_schedule_doctor_weekday` (`doctor_id`, `weekday`),
  CONSTRAINT `fk_schedule_doctor` FOREIGN KEY (`doctor_id`)
    REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_schedule_weekday` CHECK (`weekday` BETWEEN 1 AND 7),
  CONSTRAINT `chk_schedule_time` CHECK (`start_time` < `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Таблица: date_blocks — блокировка дат (отпуск, больничный, праздники)
--   doctor_id = NULL  →  блокировка действует на всю клинику (праздничный день)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `date_blocks`;
CREATE TABLE `date_blocks` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id`  INT UNSIGNED DEFAULT NULL,
  `block_date` DATE         NOT NULL,
  `reason`     VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_date_block` (`doctor_id`, `block_date`),
  KEY `idx_date_blocks_date` (`block_date`),
  CONSTRAINT `fk_block_doctor` FOREIGN KEY (`doctor_id`)
    REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Таблица: blocked_slots — заблокированные отдельные временные слоты
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `blocked_slots`;
CREATE TABLE `blocked_slots` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id`  INT UNSIGNED NOT NULL,
  `block_date` DATE         NOT NULL,
  `block_time` TIME         NOT NULL,
  `reason`     VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blocked_slot` (`doctor_id`, `block_date`, `block_time`),
  CONSTRAINT `fk_bslot_doctor` FOREIGN KEY (`doctor_id`)
    REFERENCES `doctors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Таблица: appointments — заявки на приём
--
--   active_slot_time — генерируемая колонка: NULL для отменённых заявок,
--   поэтому уникальный индекс uq_active_slot гарантирует, что один слот
--   нельзя занять дважды, но отменённая запись освобождает слот.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `ticket_number`       VARCHAR(20)   DEFAULT NULL,
  `doctor_id`           INT UNSIGNED  NOT NULL,
  `department_id`       INT UNSIGNED  NOT NULL,
  `patient_name`        VARCHAR(200)  NOT NULL,
  `patient_phone`       VARCHAR(20)   NOT NULL,
  `patient_birth_date`  DATE          DEFAULT NULL,
  `patient_passport`    VARCHAR(20)   DEFAULT NULL,
  `patient_pinfl`       VARCHAR(14)   DEFAULT NULL,
  `patient_comment`     TEXT          DEFAULT NULL,
  `appointment_date`    DATE          NOT NULL,
  `appointment_time`    TIME          NOT NULL,
  `status`              ENUM('new','confirmed','completed','cancelled') NOT NULL DEFAULT 'new',
  `ip_address`          VARCHAR(45)   DEFAULT NULL,
  `user_agent`          VARCHAR(255)  DEFAULT NULL,
  `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active_slot_time`    TIME GENERATED ALWAYS AS (IF(`status` = 'cancelled', NULL, `appointment_time`)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ticket` (`ticket_number`),
  UNIQUE KEY `uq_active_slot` (`doctor_id`, `appointment_date`, `active_slot_time`),
  KEY `idx_appointments_date` (`appointment_date`),
  KEY `idx_appointments_status` (`status`),
  KEY `idx_appointments_created` (`created_at`),
  KEY `idx_appointments_doctor` (`doctor_id`, `appointment_date`),
  CONSTRAINT `fk_appointment_doctor` FOREIGN KEY (`doctor_id`)
    REFERENCES `doctors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_appointment_department` FOREIGN KEY (`department_id`)
    REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Таблица: news — новости и официальные объявления
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `news`;
CREATE TABLE `news` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title_ru`     VARCHAR(255)  NOT NULL,
  `title_uz`     VARCHAR(255)  DEFAULT NULL,
  `title_en`     VARCHAR(255)  DEFAULT NULL,
  `excerpt_ru`   VARCHAR(500)  DEFAULT NULL,
  `excerpt_uz`   VARCHAR(500)  DEFAULT NULL,
  `excerpt_en`   VARCHAR(500)  DEFAULT NULL,
  `body_ru`      TEXT          DEFAULT NULL,
  `body_uz`      TEXT          DEFAULT NULL,
  `body_en`      TEXT          DEFAULT NULL,
  `image`        VARCHAR(255)  DEFAULT NULL,
  `is_published` TINYINT(1)    NOT NULL DEFAULT 1,
  `published_at` DATETIME      DEFAULT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_news_published` (`is_published`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Таблица: settings — настройки сайта (ключ → значение)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT         DEFAULT NULL,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Таблица: rate_limits — журнал запросов для ограничения частоты (anti-spam)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45)  NOT NULL,
  `action`     VARCHAR(50)  NOT NULL DEFAULT 'appointment',
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rate_lookup` (`ip_address`, `action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
--  НАЧАЛЬНЫЕ ДАННЫЕ
-- ============================================================================

-- Сотрудники (пароли заданы bcrypt-хэшами, совместимы с password_verify)
INSERT INTO `users` (`username`, `full_name`, `email`, `password_hash`, `role`) VALUES
('admin',       'Администратор системы',       'admin@ckb2.uz',      '$2y$12$ukDJheFHdzYR/zpL.DX5FOMLPALTLa9sqL6y0wP3Q1vxZFpJRrq.6', 'admin'),
('registratura','Оператор регистратуры ЦКБ',  'info@ckb2.uz',       '$2y$12$SwAh6nCzkjuetVVKlvJnOO5oqItykiNWHmjTIFDqad4H3JyeCQn9O', 'operator');

-- Отделения
INSERT INTO `departments` (`name_ru`, `name_uz`, `name_en`, `slug`, `description_ru`, `description_uz`, `description_en`, `icon`, `sort_order`) VALUES
('Терапевтическое отделение', 'Terapevtik bo''lim', 'Therapeutic Department', 'therapy',
 'Диагностика и лечение заболеваний внутренних органов. Диспансерное наблюдение, программы чек-ап.',
 'Ichki a''zolar kasalliklarini tashxislash va davolash. Dispanser kuzatuv va kompleks tekshiruv dasturlari.',
 'Diagnosis and treatment of internal diseases. Follow-up care and check-up programs.',
 'stethoscope', 1),
('Хирургическое отделение', 'Jarrohlik bo''limi', 'Surgical Department', 'surgery',
 'Плановая и экстренная хирургия: лапароскопические и малоинвазивные вмешательства.',
 'Rejalashtirilgan va shoshilinch jarrohlik: laparoskopik va kam invaziv aralashuvlar.',
 'Planned and emergency surgery: laparoscopic and minimally invasive procedures.',
 'scalpel', 2),
('Кардиология и кардиохирургия', 'Kardiologiya va kardioxirurgiya', 'Cardiology & Cardiac Surgery', 'cardiology',
 'Диагностика и лечение заболеваний сердца и сосудов, интервенционная кардиология, кардиохирургия.',
 'Yurak va qon tomir kasalliklarini tashxislash va davolash, intervension kardiologiya, kardioxirurgiya.',
 'Diagnosis and treatment of heart and vascular diseases, interventional cardiology, cardiac surgery.',
 'heart', 3),
('Неврология', 'Nevrologiya', 'Neurology', 'neurology',
 'Лечение заболеваний нервной системы, реабилитация после инсульта, ЭЭГ и ЭНМГ-диагностика.',
 'Nerv tizimi kasalliklarini davolash, insultdan keyin reabilitatsiya, EEG va ENMG diagnostikasi.',
 'Treatment of nervous system disorders, post-stroke rehabilitation, EEG and ENMG diagnostics.',
 'brain', 4),
('Диагностический центр (МРТ, КТ, УЗИ)', 'Diagnostika markazi (MRT, KT, UZI)', 'Diagnostics Center (MRI, CT, US)', 'diagnostics',
 'МРТ 3 Тесла, КТ 128 срезов, экспертное УЗИ и цифровая рентгенография. Результаты — в день обращения.',
 '3 Tesla MRT, 128 qatlamli KT, ekspert UZI va raqamli rentgen. Natijalar — murojaat kunining o''zida.',
 '3 Tesla MRI, 128-slice CT, expert ultrasound and digital radiography. Same-day results.',
 'scan', 5),
('Акушерство и гинекология', 'Akusherlik va ginekologiya', 'Obstetrics & Gynecology', 'gynecology',
 'Ведение беременности, гинекологическая помощь, малоинвазивные операции.',
 'Homiladorlikni kuzatish, ginekologik yordam, kam invaziv operatsiyalar.',
 'Pregnancy management, gynecological care, minimally invasive surgery.',
 'mother', 6),
('Педиатрия', 'Pediatriya', 'Pediatrics', 'pediatrics',
 'Наблюдение и лечение детей всех возрастов, вакцинация, детская неврология.',
 'Barcha yoshdagi bolalarni kuzatish va davolash, emlash, bolalar nevrologiyasi.',
 'Monitoring and treatment of children of all ages, vaccination, pediatric neurology.',
 'child', 7),
('Офтальмология', 'Oftalmologiya', 'Ophthalmology', 'ophthalmology',
 'Полная диагностика зрения, лечение глаукомы и катаракты, лазерная коррекция.',
 'Ko''rishni to''liq tashxislash, glaukoma va katarakta davolash, lazer tuzatish.',
 'Complete vision diagnostics, glaucoma and cataract treatment, laser correction.',
 'eye', 8),
('Травматология и ортопедия', 'Travmatologiya va ortopediya', 'Traumatology & Orthopedics', 'traumatology',
 'Лечение травм, эндопротезирование суставов, спортивная медицина.',
 'Travmalarni davolash, bo''g''imlarni endoprotezlash, sport tibbiyoti.',
 'Trauma treatment, joint endoprosthetics, sports medicine.',
 'bone', 9),
('Лабораторная диагностика', 'Laboratoriya diagnostikasi', 'Laboratory Diagnostics', 'laboratory',
 'Собственная клинико-диагностическая лаборатория: более 500 видов исследований.',
 'O''z klinik-diagnostik laboratoriyasi: 500 dan ortiq turdagi tadqiqotlar.',
 'In-house clinical laboratory: more than 500 types of tests.',
 'flask', 10);

-- Врачи
INSERT INTO `doctors` (`department_id`, `name_ru`, `name_uz`, `name_en`, `position_ru`, `position_uz`, `position_en`, `category`, `experience_years`, `office`, `photo`, `bio`, `slot_duration`, `sort_order`) VALUES
(1, 'Рахимов Тимур Бахтиярович', 'Raximov Timur Baxtiyorovich', 'Rakhimov Timur Bakhtiyarovich',
 'Врач-терапевт', 'Terapevt shifokori', 'Physician (Therapist)',
 'Высшая категория', 18, '204', 'doctors/doctor-1.jpg',
 'Ведёт приём пациентов с заболеваниями органов дыхания, ЖКТ и эндокринной системы. Автор программ комплексного чек-апа больницы.', 30, 1),
(3, 'Каримова Дилноза Абдуллаевна', 'Karimova Dilnoza Abdullayevna', 'Karimova Dilnoza Abdullayevna',
 'Врач-кардиолог', 'Kardiolog shifokori', 'Cardiologist',
 'Кандидат медицинских наук', 15, '310', 'doctors/doctor-2.jpg',
 'Специалист по нарушениям ритма сердца и артериальной гипертензии. Выполняет ЭхоКГ и нагрузочные пробы.', 30, 2),
(3, 'Юсупов Фаррух Рустамович', 'Yusupov Farrux Rustamovich', 'Yusupov Farrukh Rustamovich',
 'Врач-сердечно-сосудистый хирург', 'Yurak-qon tomir jarrohi', 'Cardiovascular Surgeon',
 'Доктор медицинских наук, профессор', 24, '105', 'doctors/doctor-3.jpg',
 'Заведующий отделением кардиохирургии. Более 3 000 операций на открытом сердце, включая АКШ и клапанную хирургию.', 60, 3),
(4, 'Ахмедова Гулчехра Сайфуллаевна', 'Ahmedova Gulchehra Sayfullayevna', 'Akhmedova Gulchehra Sayfullayevna',
 'Врач-невролог', 'Nevrolog shifokori', 'Neurologist',
 'Высшая категория', 16, '412', 'doctors/doctor-4.jpg',
 'Специализация: сосудистые заболевания головного мозга, головные боли, реабилитация после инсульта.', 30, 4),
(2, 'Туляганов Шухрат Анварович', 'Tulyaganov Shuhrat Anvarovich', 'Tulyaganov Shukhrat Anvarovich',
 'Врач-хирург', 'Jarroh shifokori', 'Surgeon',
 'Высшая категория', 20, '108', NULL,
 'Лапароскопическая хирургия желчевыводящих путей и грыжесечение сетчатыми имплантами.', 30, 5),
(7, 'Исмоилова Наргиз Улугбековна', 'Ismoilova Nargiz Ulug''bekovna', 'Ismoilova Nargiz Ulugbekovna',
 'Врач-педиатр', 'Pediatriya shifokori', 'Pediatrician',
 'Высшая категория', 12, '506', NULL,
 'Наблюдение детей с рождения, ведение детей групп риска, индивидуальные графики вакцинации.', 20, 6),
(9, 'Садыков Жасур Алпамысович', 'Sadiqov Jasur Alpamisovich', 'Sadykov Jasur Alpamysovich',
 'Врач-травматолог-ортопед', 'Travmatolog-ortoped shifokori', 'Traumatologist-Orthopedist',
 'Высшая категория', 14, '209', NULL,
 'Эндопротезирование тазобедренного и коленного суставов, артроскопия, лечение переломов.', 30, 7),
(6, 'Муминова Замира Хикматовна', 'Muminova Zamira Hikmatovna', 'Muminova Zamira Hikmatovna',
 'Врач-акушер-гинеколог', 'Akusher-ginekolog shifokori', 'Obstetrician-Gynecologist',
 'Кандидат медицинских наук', 19, '407', NULL,
 'Ведение беременности высокого риска, гистероскопия, программы подготовки к ЭКО.', 30, 8),
(8, 'Хамидов Ботир Рустамович', 'Hamidov Botir Rustamovich', 'Khamidov Botir Rustamovich',
 'Врач-офтальмолог', 'Oftalmolog shifokori', 'Ophthalmologist',
 'Первая категория', 11, '303', NULL,
 'Микрохирургия катаракты, лазерное лечение глаукомы и диабетической ретинопатии.', 20, 9),
(5, 'Эргашев Отабек Махмудович', 'Ergashev Otabek Mahmudovich', 'Ergashev Otabek Mahmudovich',
 'Врач-рентгенолог (МРТ/КТ)', 'Rentgenolog shifokori (MRT/KT)', 'Radiologist (MRI/CT)',
 'Кандидат медицинских наук', 17, '118', NULL,
 'Интерпретация МРТ и КТ-исследований любой сложности, прицельная диагностика онкопатологии.', 15, 10),
(5, 'Назарова Саодат Рахимжановна', 'Nazarova Saodat Rahimjanovna', 'Nazarova Saodat Rakhimzhanovna',
 'Врач ультразвуковой диагностики', 'Ultratovush diagnostikasi shifokori', 'Ultrasound Diagnostics Specialist',
 'Высшая категория', 13, '121', NULL,
 'Экспертное УЗИ органов брюшной полости, сосудов, щитовидной железы и суставов.', 20, 11),
(2, 'Фозилов Ильдар Джахонгирович', 'Fozilov Ildar Jahongirovich', 'Fozilov Ildar Jahongirovich',
 'Врач-хирург', 'Jarroh shifokori', 'Surgeon',
 'Первая категория', 9, '110', NULL,
 'Амбулаторная хирургия: удаление новообразований кожи, флебология, малоинвазивные методики.', 30, 12);

-- Недельные графики приёма (weekday: 1 = пн ... 7 = вс)
INSERT INTO `doctor_schedules` (`doctor_id`, `weekday`, `start_time`, `end_time`) VALUES
-- 1. Рахимов Т. — пн–пт 09:00–14:00
(1,1,'09:00:00','14:00:00'), (1,2,'09:00:00','14:00:00'), (1,3,'09:00:00','14:00:00'), (1,4,'09:00:00','14:00:00'), (1,5,'09:00:00','14:00:00'),
-- 2. Каримова Д. — пн/ср/пт 09:00–13:00, вт/чт 14:00–18:00
(2,1,'09:00:00','13:00:00'), (2,3,'09:00:00','13:00:00'), (2,5,'09:00:00','13:00:00'),
(2,2,'14:00:00','18:00:00'), (2,4,'14:00:00','18:00:00'),
-- 3. Юсупов Ф. — пн/ср/пт 08:00–12:00 (операционные дни вт/чт)
(3,1,'08:00:00','12:00:00'), (3,3,'08:00:00','12:00:00'), (3,5,'08:00:00','12:00:00'),
-- 4. Ахмедова Г. — пн–пт 09:00–14:00
(4,1,'09:00:00','14:00:00'), (4,2,'09:00:00','14:00:00'), (4,3,'09:00:00','14:00:00'), (4,4,'09:00:00','14:00:00'), (4,5,'09:00:00','14:00:00'),
-- 5. Туляганов Ш. — пн–пт 10:00–15:00
(5,1,'10:00:00','15:00:00'), (5,2,'10:00:00','15:00:00'), (5,3,'10:00:00','15:00:00'), (5,4,'10:00:00','15:00:00'), (5,5,'10:00:00','15:00:00'),
-- 6. Исмоилова Н. — пн–сб 09:00–13:00
(6,1,'09:00:00','13:00:00'), (6,2,'09:00:00','13:00:00'), (6,3,'09:00:00','13:00:00'), (6,4,'09:00:00','13:00:00'), (6,5,'09:00:00','13:00:00'), (6,6,'09:00:00','13:00:00'),
-- 7. Садыков Ж. — пн/ср/пт 09:00–13:00, вт/чт 14:00–17:00
(7,1,'09:00:00','13:00:00'), (7,3,'09:00:00','13:00:00'), (7,5,'09:00:00','13:00:00'),
(7,2,'14:00:00','17:00:00'), (7,4,'14:00:00','17:00:00'),
-- 8. Муминова З. — пн–пт 09:00–14:00
(8,1,'09:00:00','14:00:00'), (8,2,'09:00:00','14:00:00'), (8,3,'09:00:00','14:00:00'), (8,4,'09:00:00','14:00:00'), (8,5,'09:00:00','14:00:00'),
-- 9. Хамидов Б. — пн–пт 14:00–18:00
(9,1,'14:00:00','18:00:00'), (9,2,'14:00:00','18:00:00'), (9,3,'14:00:00','18:00:00'), (9,4,'14:00:00','18:00:00'), (9,5,'14:00:00','18:00:00'),
-- 10. Эргашев О. — пн–сб 08:00–13:00
(10,1,'08:00:00','13:00:00'), (10,2,'08:00:00','13:00:00'), (10,3,'08:00:00','13:00:00'), (10,4,'08:00:00','13:00:00'), (10,5,'08:00:00','13:00:00'), (10,6,'08:00:00','13:00:00'),
-- 11. Назарова С. — пн–пт 09:00–13:00
(11,1,'09:00:00','13:00:00'), (11,2,'09:00:00','13:00:00'), (11,3,'09:00:00','13:00:00'), (11,4,'09:00:00','13:00:00'), (11,5,'09:00:00','13:00:00'),
-- 12. Фозилов И. — пн–пт 14:00–18:00
(12,1,'14:00:00','18:00:00'), (12,2,'14:00:00','18:00:00'), (12,3,'14:00:00','18:00:00'), (12,4,'14:00:00','18:00:00'), (12,5,'14:00:00','18:00:00');

-- Блокировки дат: праздники (вся клиника) и отпуск конкретного врача
INSERT INTO `date_blocks` (`doctor_id`, `block_date`, `reason`) VALUES
(NULL, '2026-12-08', 'День Конституции Республики Узбекистан'),
(NULL, '2027-01-01', 'Новый год'),
(3, '2026-10-12', 'Ежегодный отпуск'),
(3, '2026-10-13', 'Ежегодный отпуск'),
(3, '2026-10-14', 'Ежегодный отпуск'),
(3, '2026-10-15', 'Ежегодный отпуск'),
(3, '2026-10-16', 'Ежегодный отпуск');

-- Демонстрация блокировки отдельного слота
INSERT INTO `blocked_slots` (`doctor_id`, `block_date`, `block_time`, `reason`) VALUES
(2, '2026-10-06', '11:00:00', 'Административные мероприятия');

-- Демонстрационные заявки (для наполнения дашборда)
INSERT INTO `appointments`
(`ticket_number`, `doctor_id`, `department_id`, `patient_name`, `patient_phone`, `patient_birth_date`, `patient_passport`, `patient_pinfl`, `patient_comment`, `appointment_date`, `appointment_time`, `status`, `ip_address`) VALUES
('ЦКБ2-2026-0001', 1, 1, 'Мадраимов Бекзод Рустамович', '+998901234567', '1988-04-12', 'AA1234567', NULL, 'Повторный приём, контроль анализов', '2026-10-02', '09:30:00', 'new', '127.0.0.1'),
('ЦКБ2-2026-0002', 2, 3, 'Юлдашева Мархабо Тошпулатовна', '+998935552288', '1976-11-03', NULL, NULL, 'Боли в области сердца при нагрузке', '2026-10-02', '10:00:00', 'confirmed', '127.0.0.1'),
('ЦКБ2-2026-0003', 4, 4, 'Эргашев Рустам Нигматович', '+998977771122', '1965-02-27', NULL, NULL, 'Головные боли, головокружение', '2026-10-01', '10:30:00', 'completed', '127.0.0.1'),
('ЦКБ2-2026-0004', 6, 7, 'Тохирова Азиза Шухратовна', '+998881234090', '2019-06-15', NULL, NULL, 'Плановый осмотр', '2026-10-01', '11:30:00', 'confirmed', '127.0.0.1'),
('ЦКБ2-2026-0005', 10, 5, 'Солиева Дилрабо Умидовна', '+998946660131', '1994-09-21', NULL, NULL, 'МРТ головного мозга, направление от невролога', '2026-10-05', '08:15:00', 'new', '127.0.0.1');

-- Новости
INSERT INTO `news` (`title_ru`, `title_uz`, `title_en`, `excerpt_ru`, `excerpt_uz`, `excerpt_en`, `body_ru`, `body_uz`, `body_en`, `image`, `is_published`, `published_at`) VALUES
('Введён в эксплуатацию новый магнитно-резонансный томограф 3 Тесла',
 '3 Tesla yangi magnit-rezonans tomografi ishga tushirildi',
 'New 3 Tesla MRI scanner put into operation',
 'Диагностический центр больницы пополнился экспертным МР-томографом с возможностью кардиовизуализации и нейровизуализации высокой чёткости.',
 'Diagnostika markazi kardiografik va yuqori aniqlikdagi neyrovizualizatsiya imkoniyatiga ega ekspert MRT tomografi bilan to''ldirildi.',
 'The diagnostics center has been equipped with an expert MRI scanner featuring cardiac and high-definition neuro-imaging.',
 'Диагностический центр ЦКБ № 2 пополнился новым магнитно-резонансным томографом мощностью 3 Тесла. Аппарат обеспечивает высочайшее качество изображений головного и спинного мозга, суставов и органов малого таза, позволяет выполнять МР-ангиографию и кардиовизуализацию.\n\nСрок выполнения исследования сокращён до 25 минут, заключение врача-рентгенолога выдаётся в день обращения. Запись на МРТ доступна через форму онлайн-записи и по телефону регистратуры.',
 'Tomoq markaziga 3 Tesla quvvatga ega yangi MRT o''rnatildi. Qurilma bosh va orqa miya, bo''g''imlar va to''qima organlarining eng yuqori sifatli tasvirlarini beradi.\n\nTekshiruv muddati 25 daqiqaga qisqartirildi, rentgenolog xulosasi murojaat kunining o''zida beriladi.',
 'The new 3 Tesla MRI delivers highest-quality imaging of the brain, spine, joints and pelvic organs, including MR angiography and cardiac visualization.\n\nScan time is reduced to 25 minutes; radiologist reports are issued the same day.',
 'news/news-1.jpg', 1, '2026-09-15 10:00:00'),
('ЦКБ № 2 приняла участие в международной конференции по кардиохирургии',
 'TsKB № 2 xalqaro kardioxirurgiya konferensiyasida qatnashdi',
 'CHH No. 2 took part in the international cardiac surgery conference',
 'Специалисты отделения кардиохирургии представили доклад об отдалённых результатах коронарного шунтирования.',
 'Kardioxirurgiya bo''limi mutaxassislari koronar shuntlashning uzoq muddatli natijalari haqida ma''ruza taqdim etdi.',
 'Cardiac surgery specialists presented a report on long-term outcomes of coronary bypass surgery.',
 'Сотрудники отделения кардиохирургии ЦКБ № 2 приняли участие в международной конференции «Современные технологии в хирургии сердца». В докладе заведующего отделением были представлены отдалённые результаты более 1 200 операций аортокоронарного шунтирования, выполненных в клинике.\n\nПо итогам выступления методики больницы рекомендованы к включению в национальный протокол лечения ишемической болезни сердца.',
 'Kardioxirurgiya bo''limi xodimlari «Yurak jarrohligida zamonaviy texnologiyalar» xalqaro konferensiyasida qatnashdilar va 1200 dan ortiq aortokoronar shuntlash operatsiyasi natijalarini taqdim etdilar.',
 'Cardiac surgeons of CHH No. 2 took part in the international conference "Advanced Technologies in Cardiac Surgery", presenting outcomes of over 1,200 coronary bypass operations.',
 'news/news-2.jpg', 1, '2026-09-02 12:00:00'),
('Началась вакцинация против сезонного гриппа',
 'Mavsumiy grippga qarshi emlash boshlandi',
 'Seasonal flu vaccination campaign has started',
 'Прививочный кабинет больницы открыт с понедельника по субботу с 09:00 до 15:00. Вакцинация бесплатна по полису ОМС.',
 'Emlash xonasi dushanbadan shanbagacha 09:00 dan 15:00 gacha ishlaydi. Emlash sug''urta polisi bo''yicha bepul.',
 'The vaccination office operates Monday to Saturday, 09:00–15:00. Vaccination is free with an insurance policy.',
 'В ЦКБ № 2 стартовала кампания вакцинации против сезонного гриппа. Прививочный кабинет (каб. 112, 1-й этаж) принимает пациентов с понедельника по субботу с 09:00 до 15:00.\n\nПеред вакцинацией проводится бесплатный осмотр врача. При себе необходимо иметь паспорт и прививочный сертификат (при наличии).',
 'TsKB № 2 da mavsumiy grippga qarshi emlash kampaniyasi boshlandi. Emlash xonasi (112-xona, 1-qavat) dushanbadan shanbagacha qabul qiladi.\n\nEmlashdan oldin shifokorning bepul ko''rigidan o''tish talab etiladi.',
 'The seasonal flu vaccination campaign has started at CHH No. 2. The vaccination office (room 112, 1st floor) receives patients Monday through Saturday.',
 'news/news-3.jpg', 1, '2026-09-28 09:00:00');

-- Настройки сайта
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_phone_main',      '+998712335544'),
('site_phone_registry',  '+998712335545'),
('site_phone_emergency', '103'),
('site_email',           'info@ckb2.uz'),
('site_address_ru',      'г. Ташкент, Мирзо-Улугбекский район, ул. Фаробий, 10'),
('site_address_uz',      'Toshkent sh., Mirzo Ulug''bek tumani, Farobiy ko''chasi, 10'),
('site_address_en',      'Tashkent, Mirzo Ulugbek district, 10 Farobiy str.'),
('working_hours_ru',     'Приёмное отделение — круглосуточно. Регистратура: пн–пт 08:00–18:00, сб 09:00–14:00'),
('working_hours_uz',     'Qabul bo''limi — 24 soat. Ro''yxatga olish: du–pay 08:00–18:00, shanba 09:00–14:00'),
('working_hours_en',     'Admission department — 24/7. Reception desk: Mon–Fri 08:00–18:00, Sat 09:00–14:00'),
('registry_note_ru',     'Звонок в регистратуру: ежедневно с 08:00 до 18:00'),
('registry_note_uz',     'Ro''yxatga olish xonasiga qo''ng''iroq: har kuni 08:00 dan 18:00 gacha'),
('registry_note_en',     'Call the reception desk: daily from 08:00 to 18:00');

-- ============================================================================
--  Конец скрипта
-- ============================================================================
