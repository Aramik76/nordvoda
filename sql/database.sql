-- --------------------------------------------------------
-- База данных: aramserg_norvoda
-- --------------------------------------------------------

-- --------------------------------------------------------
-- Таблица: settings (все тексты и настройки сайта)
-- --------------------------------------------------------
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL COMMENT 'Уникальный ключ настройки',
  `value` longtext DEFAULT NULL COMMENT 'Значение настройки',
  `type` enum('text','textarea','image','phone','email','url','color') DEFAULT 'text' COMMENT 'Тип поля',
  `group` varchar(50) DEFAULT 'general' COMMENT 'Группа настроек',
  `label` varchar(255) DEFAULT NULL COMMENT 'Человеко-понятное название',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Таблица: gallery (медиабиблиотека)
-- --------------------------------------------------------
CREATE TABLE `gallery` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL COMMENT 'Имя файла',
  `original_name` varchar(255) DEFAULT NULL COMMENT 'Оригинальное имя',
  `filepath` varchar(500) NOT NULL COMMENT 'Путь к файлу',
  `thumbnail` varchar(500) DEFAULT NULL COMMENT 'Путь к миниатюре',
  `alt_text` varchar(255) DEFAULT NULL COMMENT 'Alt текст',
  `title` varchar(255) DEFAULT NULL COMMENT 'Заголовок (title)',
  `description` text DEFAULT NULL COMMENT 'Описание',
  `filesize` int(11) DEFAULT NULL COMMENT 'Размер в байтах',
  `mime_type` varchar(100) DEFAULT NULL COMMENT 'Тип файла',
  `width` int(11) DEFAULT NULL COMMENT 'Ширина (для изображений)',
  `height` int(11) DEFAULT NULL COMMENT 'Высота (для изображений)',
  `sort_order` int(11) DEFAULT 0 COMMENT 'Порядок сортировки',
  `is_published` tinyint(1) DEFAULT 1 COMMENT 'Опубликовано',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sort_order` (`sort_order`),
  KEY `is_published` (`is_published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Таблица: services (услуги бурения)
-- --------------------------------------------------------
CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Название услуги',
  `slug` varchar(255) DEFAULT NULL COMMENT 'URL-псевдоним',
  `description` text DEFAULT NULL COMMENT 'Краткое описание',
  `full_description` longtext DEFAULT NULL COMMENT 'Полное описание',
  `icon` varchar(255) DEFAULT NULL COMMENT 'Иконка (класс FontAwesome/SVG)',
  `image_id` int(11) DEFAULT NULL COMMENT 'ID из галереи (основное фото)',
  `price` varchar(100) DEFAULT NULL COMMENT 'Цена/тариф',
  `features` text DEFAULT NULL COMMENT 'Особенности (JSON)',
  `sort_order` int(11) DEFAULT 0,
  `is_published` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `image_id` (`image_id`),
  CONSTRAINT `services_ibfk_1` FOREIGN KEY (`image_id`) REFERENCES `gallery` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Таблица: projects (портфолио/выполненные работы)
-- --------------------------------------------------------
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `client` varchar(255) DEFAULT NULL COMMENT 'Заказчик',
  `location` varchar(255) DEFAULT NULL COMMENT 'Местоположение',
  `depth` varchar(100) DEFAULT NULL COMMENT 'Глубина скважины',
  `description` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `cover_image_id` int(11) DEFAULT NULL COMMENT 'Обложка',
  `completion_date` date DEFAULT NULL COMMENT 'Дата завершения',
  `sort_order` int(11) DEFAULT 0,
  `is_published` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `cover_image_id` (`cover_image_id`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`cover_image_id`) REFERENCES `gallery` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Таблица: projects_gallery (привязка фото к проекту)
-- --------------------------------------------------------
CREATE TABLE `projects_gallery` (
  `project_id` int(11) NOT NULL,
  `gallery_id` int(11) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`project_id`,`gallery_id`),
  KEY `gallery_id` (`gallery_id`),
  CONSTRAINT `projects_gallery_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `projects_gallery_ibfk_2` FOREIGN KEY (`gallery_id`) REFERENCES `gallery` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Таблица: contacts (контакты - альтернативный способ)
-- --------------------------------------------------------
-- Можно хранить контакты в таблице settings, но для сложной структуры:
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('phone','email','address','social','work_hours') NOT NULL,
  `value` varchar(500) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_published` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Таблица: users (администраторы)
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `avatar_id` int(11) DEFAULT NULL,
  `role` enum('admin','editor') DEFAULT 'editor',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Базовые настройки сайта
-- --------------------------------------------------------
INSERT INTO `settings` (`key`, `value`, `type`, `group`, `label`) VALUES
-- Основная информация
('site_name', 'БурСервис', 'text', 'general', 'Название компании'),
('site_logo', '/assets/img/logo.png', 'image', 'general', 'Логотип'),
('site_favicon', '/assets/img/favicon.ico', 'image', 'general', 'Favicon'),
('site_description', 'Профессиональное бурение скважин на воду. Работаем по всей области.', 'textarea', 'seo', 'Meta-описание'),
('site_keywords', 'бурение скважин, вода на участке, скважина под ключ', 'text', 'seo', 'Ключевые слова'),

-- Главный экран (Hero)
('hero_title', 'Бурение скважин на воду', 'text', 'home', 'Заголовок главного экрана'),
('hero_subtitle', 'от 1500 руб/метр', 'text', 'home', 'Подзаголовок'),
('hero_description', 'Быстро, качественно, с гарантией до 5 лет', 'textarea', 'home', 'Описание'),
('hero_button_text', 'Заказать звонок', 'text', 'home', 'Текст кнопки'),
('hero_button_link', '#contact', 'url', 'home', 'Ссылка кнопки'),
('hero_background', '/assets/img/hero-bg.jpg', 'image', 'home', 'Фоновое изображение'),

-- О компании
('about_title', 'О компании БурСервис', 'text', 'about', 'Заголовок блока "О нас"'),
('about_text', '<p>Мы работаем с 2010 года. За это время пробурили более 500 скважин.</p><p>Используем современное оборудование и даем гарантию на все работы.</p>', 'textarea', 'about', 'Текст о компании'),
('about_image', '/assets/img/about.jpg', 'image', 'about', 'Изображение о компании'),

-- Контакты
('contact_phone', '+7 (999) 123-45-67', 'phone', 'contacts', 'Основной телефон'),
('contact_phone_2', '+7 (999) 765-43-21', 'phone', 'contacts', 'Дополнительный телефон'),
('contact_email', 'info@burservice.ru', 'email', 'contacts', 'Email'),
('contact_address', 'г. Москва, ул. Строителей, д. 15', 'text', 'contacts', 'Адрес'),
('contact_map', 'https://yandex.ru/map-embed', 'url', 'contacts', 'Ссылка на карту'),
('work_hours', 'Пн-Пт: 9:00-18:00, Сб: 10:00-16:00', 'text', 'contacts', 'Режим работы'),

-- Социальные сети
('social_vk', 'https://vk.com/burservice', 'url', 'social', 'ВКонтакте'),
('social_telegram', 'https://t.me/burservice', 'url', 'social', 'Telegram'),
('social_whatsapp', 'https://wa.me/79991234567', 'url', 'social', 'WhatsApp'),

-- Футер
('footer_text', '© 2024 БурСервис. Все права защищены.', 'text', 'footer', 'Текст в подвале'),
('footer_description', 'Профессиональное бурение скважин любой сложности', 'textarea', 'footer', 'Описание в подвале');

-- --------------------------------------------------------
-- Демо-услуги
-- --------------------------------------------------------
INSERT INTO `services` (`title`, `description`, `price`, `features`, `sort_order`) VALUES
('Бурение на воду', 'Бурение скважин на песок и известняк', 'от 1800 руб/м', '["Гарантия 3 года", "Обсадная труба", "Фильтр в комплекте"]', 1),
('Монтаж насоса', 'Установка и подключение погружного насоса', 'от 5000 руб', '["Любые модели", "Автоматика", "Пусконаладка"]', 2),
('Обслуживание скважин', 'Чистка и ремонт существующих скважин', 'от 3000 руб', '["Диагностика", "Промывка", "Замена фильтров"]', 3);

-- --------------------------------------------------------
-- Демо-контакты
-- --------------------------------------------------------
INSERT INTO `contacts` (`type`, `value`, `icon`, `label`, `sort_order`) VALUES
('phone', '+7 (999) 123-45-67', 'phone', 'Отдел бурения', 1),
('phone', '+7 (999) 765-43-21', 'phone', 'Сервисная служба', 2),
('email', 'info@burservice.ru', 'envelope', 'Общие вопросы', 3),
('address', 'г. Москва, ул. Строителей, д. 15', 'map-pin', 'Офис', 4),
('work_hours', 'Пн-Пт: 9:00-18:00, Сб-Вс: выходной', 'clock', 'Режим работы', 5);

-- --------------------------------------------------------
-- Администратор (пароль: admin123)
-- --------------------------------------------------------
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`) VALUES
('admin', 'admin@burservice.ru', '$2y$10$b1W/7DmubCLuN3IkKvFkkumpiK6e2ONe4DUtvI/R9jG6Eit60cqdy', 'Главный администратор', 'admin');
-- Хеш соответствует паролю "password" (для демо замените на свой)