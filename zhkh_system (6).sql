-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Май 03 2026 г., 01:36
-- Версия сервера: 8.0.30
-- Версия PHP: 8.1.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `zhkh_system`
--

DELIMITER $$
--
-- Процедуры
--
CREATE DEFINER=`root`@`%` PROCEDURE `generate_invoice` (IN `p_apartment_id` INT, IN `p_month` VARCHAR(7))   BEGIN
    DECLARE v_total DECIMAL(10,2) DEFAULT 0;
    DECLARE v_details TEXT;
    
    SELECT CONCAT('[',
        GROUP_CONCAT(
            JSON_OBJECT(
                'meter_type', mt.type_name,
                'difference', ROUND(mr.difference, 2),
                'price', t.price_per_unit,
                'amount', ROUND(mr.difference * t.price_per_unit, 2)
            )
        ),
    ']')
    INTO v_details
    FROM meter_readings mr
    JOIN meter_types mt ON mr.meter_type_id = mt.meter_type_id
    JOIN tariffs t ON mt.meter_type_id = t.meter_type_id AND t.is_active = 1
    WHERE mr.apartment_id = p_apartment_id
      AND mr.reading_month = p_month
      AND mr.status = 'approved';
    
    SELECT COALESCE(SUM(ROUND(mr.difference * t.price_per_unit, 2)), 0)
    INTO v_total
    FROM meter_readings mr
    JOIN tariffs t ON mr.meter_type_id = t.meter_type_id AND t.is_active = 1
    WHERE mr.apartment_id = p_apartment_id
      AND mr.reading_month = p_month
      AND mr.status = 'approved';
    
    INSERT INTO invoices (
        apartment_id, 
        invoice_month, 
        total_amount, 
        details_json, 
        due_date,
        payment_status,
        created_at
    ) VALUES (
        p_apartment_id,
        p_month,
        v_total,
        COALESCE(v_details, '[]'),
        DATE_ADD(CONCAT(p_month, '-01'), INTERVAL 19 DAY),  -- ← ИСПРАВЛЕНО!
        'unpaid',
        NOW()
    ) ON DUPLICATE KEY UPDATE
        total_amount = VALUES(total_amount),
        details_json = VALUES(details_json),
        updated_at = NOW();
    
    IF v_total > 0 THEN
        INSERT INTO notifications (user_id, title, message, type, link_url, created_at)
        SELECT 
            a.owner_user_id,
            'Новая квитанция',
            CONCAT('Сформирована квитанция за ', DATE_FORMAT(CONCAT(p_month, '-01'), '%Y-%m'), '. Сумма: ', ROUND(v_total, 2), ' ₽'),
            'invoice',
            CONCAT('invoices.php?month=', p_month),
            NOW()
        FROM apartments a
        WHERE a.apartment_id = p_apartment_id
          AND a.owner_user_id IS NOT NULL;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `apartments`
--

CREATE TABLE `apartments` (
  `apartment_id` int NOT NULL,
  `apartment_number` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `floor` int DEFAULT NULL,
  `entrance` int DEFAULT NULL,
  `area` decimal(6,2) DEFAULT NULL,
  `rooms_count` int DEFAULT NULL,
  `residents_count` int DEFAULT '1',
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `apartments`
--

INSERT INTO `apartments` (`apartment_id`, `apartment_number`, `floor`, `entrance`, `area`, `rooms_count`, `residents_count`, `address`, `owner_user_id`, `created_at`, `updated_at`) VALUES
(1, '1', 1, 1, '45.50', 2, 3, 'г. Москва, ул. Ленина, д. 15', 3, '2026-04-09 13:52:33', '2026-04-09 13:52:33'),
(2, '2', 1, 1, '52.30', 2, 2, 'г. Москва, ул. Ленина, д. 15', NULL, '2026-04-09 13:52:33', '2026-04-09 13:52:33'),
(3, '3', 1, 1, '65.80', 3, 4, 'г. Москва, ул. Ленина, д. 15', NULL, '2026-04-09 13:52:33', '2026-04-09 13:52:33'),
(4, '36', 9, 2, '72.10', 3, 3, 'г. Москва, ул. Ленина, д. 15', NULL, '2026-04-09 13:52:33', '2026-04-09 13:52:33');

-- --------------------------------------------------------

--
-- Структура таблицы `incidents`
--

CREATE TABLE `incidents` (
  `incident_id` int NOT NULL,
  `apartment_id` int NOT NULL,
  `category_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('new','assigned','in_progress','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'new',
  `priority` enum('low','medium','high','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `photos_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int NOT NULL,
  `assigned_worker_id` int DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `rating` int DEFAULT NULL,
  `feedback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `incidents`
--

INSERT INTO `incidents` (`incident_id`, `apartment_id`, `category_id`, `title`, `description`, `status`, `priority`, `photos_json`, `created_by`, `assigned_worker_id`, `assigned_at`, `started_at`, `completed_at`, `rating`, `feedback`, `created_at`, `updated_at`) VALUES
(1, 1, 3, 'Не работает свет на лестничной площадке', 'Не горит лампочка на 9 этаже, подъезд 2', 'in_progress', 'medium', NULL, 3, 1, '2026-02-21 09:00:00', NULL, NULL, NULL, NULL, '2026-04-09 13:52:33', '2026-04-09 13:52:33'),
(2, 1, 2, 'Течет кран в общем туалете', 'На 2 этаже в общем туалете постоянно капает вода из крана', 'completed', 'high', NULL, 3, 2, '2026-02-15 07:00:00', NULL, NULL, NULL, NULL, '2026-04-09 13:52:33', '2026-04-09 13:52:33');

-- --------------------------------------------------------

--
-- Структура таблицы `incident_categories`
--

CREATE TABLE `incident_categories` (
  `category_id` int NOT NULL,
  `category_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `icon_class` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority_level` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `incident_categories`
--

INSERT INTO `incident_categories` (`category_id`, `category_name`, `description`, `icon_class`, `priority_level`, `created_at`) VALUES
(1, 'Лифт', 'Проблемы с лифтом', 'fa-elevator', 3, '2026-04-09 13:52:33'),
(2, 'Протечка', 'Протечки воды, канализации', 'fa-water', 3, '2026-04-09 13:52:33'),
(3, 'Освещение', 'Проблемы с освещением', 'fa-lightbulb', 2, '2026-04-09 13:52:33'),
(4, 'Отопление', 'Проблемы с отоплением', 'fa-temperature-high', 3, '2026-04-09 13:52:33'),
(5, 'Электричество', 'Проблемы с электричеством', 'fa-bolt', 3, '2026-04-09 13:52:33'),
(6, 'Сантехника', 'Сантехнические проблемы', 'fa-wrench', 2, '2026-04-09 13:52:33'),
(7, 'Вентиляция', 'Проблемы с вентиляцией', 'fa-wind', 2, '2026-04-09 13:52:33'),
(8, 'Домофон', 'Неисправности домофона', 'fa-phone', 1, '2026-04-09 13:52:33'),
(9, 'Другое', 'Прочие проблемы', 'fa-tools', 1, '2026-04-09 13:52:33');

-- --------------------------------------------------------

--
-- Структура таблицы `incident_comments`
--

CREATE TABLE `incident_comments` (
  `comment_id` int NOT NULL,
  `incident_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `incident_history`
--

CREATE TABLE `incident_history` (
  `history_id` int NOT NULL,
  `incident_id` int NOT NULL,
  `changed_by` int NOT NULL,
  `old_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `change_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `invoices`
--

CREATE TABLE `invoices` (
  `invoice_id` int NOT NULL,
  `apartment_id` int NOT NULL,
  `invoice_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(10,2) DEFAULT '0.00',
  `payment_status` enum('unpaid','partial','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'unpaid',
  `payment_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `details_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pdf_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `invoices`
--

INSERT INTO `invoices` (`invoice_id`, `apartment_id`, `invoice_month`, `total_amount`, `paid_amount`, `payment_status`, `payment_date`, `due_date`, `details_json`, `pdf_path`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-02', '4580.50', '4580.50', 'paid', '2026-03-01', '2026-03-10', NULL, NULL, NULL, NULL, '2026-04-09 13:52:33', '2026-04-09 13:52:33'),
(2, 1, '2026-01', '4320.00', '4320.00', 'paid', '2026-02-01', '2026-02-10', NULL, NULL, NULL, NULL, '2026-04-09 13:52:33', '2026-04-09 13:52:33'),
(3, 1, '2026-03', '4750.20', '0.00', 'unpaid', NULL, '2026-04-10', NULL, NULL, NULL, NULL, '2026-04-09 13:52:33', '2026-04-09 13:52:33');

-- --------------------------------------------------------

--
-- Структура таблицы `meter_readings`
--

CREATE TABLE `meter_readings` (
  `reading_id` int NOT NULL,
  `apartment_id` int NOT NULL,
  `meter_type_id` int NOT NULL,
  `reading_value` decimal(10,2) NOT NULL,
  `reading_date` date NOT NULL,
  `reading_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_value` decimal(10,2) DEFAULT NULL,
  `difference` decimal(10,2) DEFAULT NULL,
  `photos_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `meter_readings`
--

INSERT INTO `meter_readings` (`reading_id`, `apartment_id`, `meter_type_id`, `reading_value`, `reading_date`, `reading_month`, `previous_value`, `difference`, `photos_json`, `status`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 1, '125.30', '2026-02-21', '2026-02', NULL, '0.00', NULL, 'approved', NULL, 3, '2026-04-09 13:52:33'),
(2, 1, 2, '95.70', '2026-02-21', '2026-02', NULL, '0.00', NULL, 'approved', NULL, 3, '2026-04-09 13:52:33'),
(3, 1, 3, '5421.00', '2026-02-21', '2026-02', NULL, '0.00', NULL, 'approved', NULL, 3, '2026-04-09 13:52:33'),
(4, 1, 1, '125.33', '2026-03-15', '2026-03', '125.30', '0.03', NULL, 'pending', NULL, 3, '2026-04-09 13:52:33'),
(5, 1, 2, '95.74', '2026-03-15', '2026-03', '95.70', '0.04', NULL, 'pending', NULL, 3, '2026-04-09 13:52:33'),
(6, 1, 3, '5421.03', '2026-03-15', '2026-03', '5421.00', '0.03', NULL, 'pending', NULL, 3, '2026-04-09 13:52:33'),
(7, 1, 4, '0.05', '2026-03-15', '2026-03', NULL, '0.00', NULL, 'pending', NULL, 3, '2026-04-09 13:52:33');

--
-- Триггеры `meter_readings`
--
DELIMITER $$
CREATE TRIGGER `before_insert_meter_reading` BEFORE INSERT ON `meter_readings` FOR EACH ROW BEGIN
    DECLARE prev_val DECIMAL(10,2);
    SELECT reading_value INTO prev_val
    FROM meter_readings
    WHERE apartment_id   = NEW.apartment_id
      AND meter_type_id  = NEW.meter_type_id
      AND reading_date   < NEW.reading_date
    ORDER BY reading_date DESC LIMIT 1;
    SET NEW.previous_value = prev_val;
    SET NEW.difference = NEW.reading_value - IFNULL(prev_val, NEW.reading_value);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `meter_types`
--

CREATE TABLE `meter_types` (
  `meter_type_id` int NOT NULL,
  `type_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `icon_class` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `meter_types`
--

INSERT INTO `meter_types` (`meter_type_id`, `type_name`, `unit`, `description`, `icon_class`, `created_at`) VALUES
(1, 'Холодная вода', 'м³', 'Холодное водоснабжение', 'fa-tint', '2026-04-09 13:52:33'),
(2, 'Горячая вода', 'м³', 'Горячее водоснабжение', 'fa-fire', '2026-04-09 13:52:33'),
(3, 'Электроэнергия', 'кВт⋅ч', 'Электрическая энергия', 'fa-bolt', '2026-04-09 13:52:33'),
(4, 'Отопление', 'Гкал', 'Теплоснабжение', 'fa-temperature-high', '2026-04-09 13:52:33');

-- --------------------------------------------------------

--
-- Структура таблицы `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int NOT NULL,
  `user_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('info','warning','success','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT '0',
  `link_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `is_read`, `link_url`, `created_at`) VALUES
(1, 3, 'Показания приняты', 'Показания за 03.2026 приняты', 'success', 0, 'meters.php', '2026-04-09 13:52:33'),
(2, 1, 'Новая заявка на регистрацию', 'Пользователь Иванов Иван Иванович (3@gmail.com) подал заявку на регистрацию', 'info', 0, 'admin.php?tab=registrations', '2026-05-01 20:34:03');

-- --------------------------------------------------------

--
-- Структура таблицы `registration_requests`
--

CREATE TABLE `registration_requests` (
  `request_id` int NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apartment_number` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `reviewed_by` int DEFAULT NULL,
  `review_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `registration_requests`
--

INSERT INTO `registration_requests` (`request_id`, `email`, `password_hash`, `full_name`, `phone`, `apartment_number`, `status`, `reviewed_by`, `review_note`, `reviewed_at`, `created_at`) VALUES
(1, '3@gmail.com', '$2y$10$.ytwNXXtyzz/L33h5f69we7ENgaIfZRM.zpl0ySbZh4ciNOQiRyEC', 'Иванов Иван Иванович', '+79001111110', '34', 'pending', NULL, NULL, NULL, '2026-05-01 20:34:03');

-- --------------------------------------------------------

--
-- Структура таблицы `roles`
--

CREATE TABLE `roles` (
  `role_id` int NOT NULL,
  `role_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_description`, `created_at`) VALUES
(1, 'admin', 'Администратор системы — полный доступ ко всем функциям', '2026-04-09 13:52:33'),
(2, 'worker', 'Работник/мастер — просмотр и обработка назначенных заявок', '2026-04-09 13:52:33'),
(3, 'resident', 'Жилец — базовый функционал для жильцов', '2026-04-09 13:52:33');

-- --------------------------------------------------------

--
-- Структура таблицы `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int NOT NULL,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `setting_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'site_name', 'ДомУчет - Система управления ЖКХ', 'string', 'Название сайта', '2026-04-09 13:52:33'),
(2, 'meter_reminder_day', '25', 'integer', 'День месяца для напоминания о передаче показаний', '2026-04-09 13:52:33'),
(3, 'payment_due_day', '10', 'integer', 'День месяца для оплаты квитанций', '2026-04-09 13:52:33'),
(4, 'enable_email_notifications', '1', 'boolean', 'Включить email уведомления', '2026-04-09 13:52:33'),
(5, 'enable_sms_notifications', '0', 'boolean', 'Включить SMS уведомления', '2026-04-09 13:52:33'),
(6, 'allow_registration', '1', 'boolean', 'Разрешить самостоятельную регистрацию жильцов', '2026-04-09 13:52:33');

-- --------------------------------------------------------

--
-- Структура таблицы `tariffs`
--

CREATE TABLE `tariffs` (
  `tariff_id` int NOT NULL,
  `meter_type_id` int NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `tariffs`
--

INSERT INTO `tariffs` (`tariff_id`, `meter_type_id`, `price_per_unit`, `valid_from`, `valid_to`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, '42.50', '2026-01-01', NULL, 'Холодное водоснабжение на 2026 год', 1, '2026-04-09 13:52:33', '2026-04-09 13:52:33'),
(2, 2, '185.30', '2026-01-01', NULL, 'Горячее водоснабжение на 2026 год', 1, '2026-04-09 13:52:33', '2026-04-09 13:52:33'),
(3, 3, '6.85', '2026-01-01', NULL, 'Электроэнергия (однотарифный)', 1, '2026-04-09 13:52:33', '2026-04-09 13:52:33'),
(4, 4, '2150.00', '2026-01-01', NULL, 'Отопление на 2026 год', 1, '2026-04-09 13:52:33', '2026-04-09 13:52:33');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_id` int NOT NULL DEFAULT '3',
  `avatar_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`user_id`, `email`, `password_hash`, `full_name`, `phone`, `role_id`, `avatar_path`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin@dom.ru', '$2y$10$zgoueLsZDKXT1Fh9mIuSLeSGod0nyqGjBElptvE0fetonGFTFuxBC', 'Петров Андрей Миланович', '+7 (900) 111-11-11', 1, 'avatars/avatar_1_1773514324.jpg', 1, '2026-05-02 03:27:03', '2026-04-09 13:52:33', '2026-05-02 03:27:03'),
(3, 'resident@dom.ru', '$2y$10$xrGXUeIzZs9NnaE9C5bca.s/e0NIMMu47VVDOK5AmaOlxNm.MUv3.', 'Сидоров Петр Александрович', '+7 (900) 333-33-33', 3, 'avatars/avatar_3_1773512809.jpg', 1, '2026-05-02 03:28:54', '2026-04-09 13:52:33', '2026-05-02 03:28:54');

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_active_incidents`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_active_incidents` (
`apartment_number` varchar(10)
,`category_name` varchar(100)
,`created_at` timestamp
,`description` text
,`incident_id` int
,`priority` enum('low','medium','high','urgent')
,`reporter_name` varchar(150)
,`status` enum('new','assigned','in_progress','completed','cancelled')
,`title` varchar(255)
,`updated_at` timestamp
,`worker_name` varchar(150)
);

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_apartment_statistics`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_apartment_statistics` (
`address` varchar(255)
,`apartment_id` int
,`apartment_number` varchar(10)
,`owner_email` varchar(100)
,`owner_name` varchar(150)
,`total_debt` decimal(32,2)
,`total_incidents` bigint
,`total_invoices` bigint
,`total_readings` bigint
);

-- --------------------------------------------------------

--
-- Структура таблицы `workers`
--

CREATE TABLE `workers` (
  `worker_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `full_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specialization` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `rating` decimal(3,2) DEFAULT '0.00',
  `completed_tasks` int DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `workers`
--

INSERT INTO `workers` (`worker_id`, `user_id`, `full_name`, `phone`, `email`, `specialization`, `is_active`, `rating`, `completed_tasks`, `notes`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Иванов Иван Петрович', '+7 (900) 444-44-44', 'master1@dom.ru', 'Электрик', 1, '4.80', 156, NULL, '2026-04-09 13:52:33', '2026-04-09 13:52:33'),
(2, NULL, 'Петров Сергей Александрович', '+7 (900) 555-55-55', 'master2@dom.ru', 'Сантехник', 1, '4.90', 203, NULL, '2026-04-09 13:52:33', '2026-04-09 13:52:33'),
(3, NULL, 'Сидоров Алексей Владимирович', '+7 (900) 666-66-66', 'master3@dom.ru', 'Слесарь', 1, '4.70', 89, NULL, '2026-04-09 13:52:33', '2026-04-09 13:52:33');

-- --------------------------------------------------------

--
-- Структура для представления `v_active_incidents`
--
DROP TABLE IF EXISTS `v_active_incidents`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_active_incidents`  AS SELECT `i`.`incident_id` AS `incident_id`, `i`.`title` AS `title`, `i`.`description` AS `description`, `i`.`status` AS `status`, `i`.`priority` AS `priority`, `a`.`apartment_number` AS `apartment_number`, `u`.`full_name` AS `reporter_name`, `ic`.`category_name` AS `category_name`, `w`.`full_name` AS `worker_name`, `i`.`created_at` AS `created_at`, `i`.`updated_at` AS `updated_at` FROM ((((`incidents` `i` join `apartments` `a` on((`i`.`apartment_id` = `a`.`apartment_id`))) join `users` `u` on((`i`.`created_by` = `u`.`user_id`))) join `incident_categories` `ic` on((`i`.`category_id` = `ic`.`category_id`))) left join `workers` `w` on((`i`.`assigned_worker_id` = `w`.`worker_id`))) WHERE (`i`.`status` not in ('completed','cancelled')) ORDER BY (case `i`.`priority` when 'urgent' then 1 when 'high' then 2 when 'medium' then 3 else 4 end) ASC, `i`.`created_at` ASC  ;

-- --------------------------------------------------------

--
-- Структура для представления `v_apartment_statistics`
--
DROP TABLE IF EXISTS `v_apartment_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_apartment_statistics`  AS SELECT `a`.`apartment_id` AS `apartment_id`, `a`.`apartment_number` AS `apartment_number`, `a`.`address` AS `address`, `u`.`full_name` AS `owner_name`, `u`.`email` AS `owner_email`, count(distinct `mr`.`reading_id`) AS `total_readings`, count(distinct `inv`.`invoice_id`) AS `total_invoices`, sum((case when (`inv`.`payment_status` = 'unpaid') then `inv`.`total_amount` else 0 end)) AS `total_debt`, count(distinct `inc`.`incident_id`) AS `total_incidents` FROM ((((`apartments` `a` left join `users` `u` on((`a`.`owner_user_id` = `u`.`user_id`))) left join `meter_readings` `mr` on((`a`.`apartment_id` = `mr`.`apartment_id`))) left join `invoices` `inv` on((`a`.`apartment_id` = `inv`.`apartment_id`))) left join `incidents` `inc` on((`a`.`apartment_id` = `inc`.`apartment_id`))) GROUP BY `a`.`apartment_id``apartment_id`  ;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `apartments`
--
ALTER TABLE `apartments`
  ADD PRIMARY KEY (`apartment_id`),
  ADD KEY `idx_apartment_number` (`apartment_number`),
  ADD KEY `idx_owner` (`owner_user_id`);

--
-- Индексы таблицы `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`incident_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_apartment` (`apartment_id`),
  ADD KEY `idx_worker` (`assigned_worker_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_incidents_priority_status` (`priority`,`status`);

--
-- Индексы таблицы `incident_categories`
--
ALTER TABLE `incident_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Индексы таблицы `incident_comments`
--
ALTER TABLE `incident_comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_incident` (`incident_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Индексы таблицы `incident_history`
--
ALTER TABLE `incident_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_incident` (`incident_id`);

--
-- Индексы таблицы `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `unique_invoice` (`apartment_id`,`invoice_month`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_apartment_month` (`apartment_id`,`invoice_month`),
  ADD KEY `idx_status` (`payment_status`),
  ADD KEY `idx_invoices_due_date` (`due_date`),
  ADD KEY `idx_invoices_payment_status` (`payment_status`,`apartment_id`);

--
-- Индексы таблицы `meter_readings`
--
ALTER TABLE `meter_readings`
  ADD PRIMARY KEY (`reading_id`),
  ADD UNIQUE KEY `unique_reading` (`apartment_id`,`meter_type_id`,`reading_month`),
  ADD KEY `meter_type_id` (`meter_type_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_apartment_month` (`apartment_id`,`reading_month`),
  ADD KEY `idx_date` (`reading_date`),
  ADD KEY `idx_meter_readings_status` (`status`,`apartment_id`);

--
-- Индексы таблицы `meter_types`
--
ALTER TABLE `meter_types`
  ADD PRIMARY KEY (`meter_type_id`),
  ADD UNIQUE KEY `type_name` (`type_name`);

--
-- Индексы таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Индексы таблицы `registration_requests`
--
ALTER TABLE `registration_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `uq_reg_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Индексы таблицы `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Индексы таблицы `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Индексы таблицы `tariffs`
--
ALTER TABLE `tariffs`
  ADD PRIMARY KEY (`tariff_id`),
  ADD KEY `idx_active_tariffs` (`meter_type_id`,`is_active`,`valid_from`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role_id`);

--
-- Индексы таблицы `workers`
--
ALTER TABLE `workers`
  ADD PRIMARY KEY (`worker_id`),
  ADD UNIQUE KEY `uq_worker_user` (`user_id`),
  ADD KEY `idx_specialization` (`specialization`),
  ADD KEY `idx_active` (`is_active`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `apartments`
--
ALTER TABLE `apartments`
  MODIFY `apartment_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `incidents`
--
ALTER TABLE `incidents`
  MODIFY `incident_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `incident_categories`
--
ALTER TABLE `incident_categories`
  MODIFY `category_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `incident_comments`
--
ALTER TABLE `incident_comments`
  MODIFY `comment_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `incident_history`
--
ALTER TABLE `incident_history`
  MODIFY `history_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `invoices`
--
ALTER TABLE `invoices`
  MODIFY `invoice_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `meter_readings`
--
ALTER TABLE `meter_readings`
  MODIFY `reading_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `meter_types`
--
ALTER TABLE `meter_types`
  MODIFY `meter_type_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `registration_requests`
--
ALTER TABLE `registration_requests`
  MODIFY `request_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `tariffs`
--
ALTER TABLE `tariffs`
  MODIFY `tariff_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `workers`
--
ALTER TABLE `workers`
  MODIFY `worker_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `apartments`
--
ALTER TABLE `apartments`
  ADD CONSTRAINT `apartments_ibfk_1` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `incidents`
--
ALTER TABLE `incidents`
  ADD CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`apartment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incidents_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `incident_categories` (`category_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `incidents_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `incidents_ibfk_4` FOREIGN KEY (`assigned_worker_id`) REFERENCES `workers` (`worker_id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `incident_comments`
--
ALTER TABLE `incident_comments`
  ADD CONSTRAINT `incident_comments_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incident_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `incident_history`
--
ALTER TABLE `incident_history`
  ADD CONSTRAINT `incident_history_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`incident_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incident_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`apartment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `meter_readings`
--
ALTER TABLE `meter_readings`
  ADD CONSTRAINT `meter_readings_ibfk_1` FOREIGN KEY (`apartment_id`) REFERENCES `apartments` (`apartment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meter_readings_ibfk_2` FOREIGN KEY (`meter_type_id`) REFERENCES `meter_types` (`meter_type_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `meter_readings_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `registration_requests`
--
ALTER TABLE `registration_requests`
  ADD CONSTRAINT `reg_req_ibfk_1` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `tariffs`
--
ALTER TABLE `tariffs`
  ADD CONSTRAINT `tariffs_ibfk_1` FOREIGN KEY (`meter_type_id`) REFERENCES `meter_types` (`meter_type_id`) ON DELETE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE RESTRICT;

--
-- Ограничения внешнего ключа таблицы `workers`
--
ALTER TABLE `workers`
  ADD CONSTRAINT `workers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
