<?php
// ==============================================
// install.php - Скрипт установки сайта с ручным вводом параметров БД
// ==============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Проверка версии PHP
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('❌ Требуется PHP 7.4 или выше. Ваша версия: ' . PHP_VERSION);
}

// Проверка расширений
$required_extensions = ['pdo', 'pdo_mysql', 'gd', 'json', 'session'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    die('❌ Отсутствуют расширения: ' . implode(', ', $missing_extensions));
}

// Функция для проверки прав на запись
function is_writable_path($path) {
    if (!file_exists($path)) {
        return mkdir($path, 0777, true);
    }
    return is_writable($path);
}

// Функция для логирования
function log_installation($message, $type = 'info') {
    $log_file = __DIR__ . '/logs/install.log';
    $log_dir = dirname($log_file);
    
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $type: $message\n", FILE_APPEND);
}

// Обработка сброса установки
if (isset($_GET['reset'])) {
    session_destroy();
    header('Location: install.php');
    exit;
}

// ШАГ 1: Проверка окружения (всегда выполняется)
$env_ok = true;
$env_output = '';

// Версия PHP
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    $env_output .= '<p>📌 PHP версия: ' . PHP_VERSION . ' <span style="color:#10B981">✓ OK</span></p>';
} else {
    $env_output .= '<p>📌 PHP версия: ' . PHP_VERSION . ' <span style="color:#EF4444">✗ Требуется PHP 7.4+</span></p>';
    $env_ok = false;
}

// Расширения
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        $env_output .= '<p>📌 Расширение ' . $ext . ': <span style="color:#10B981">✓ Загружено</span></p>';
    } else {
        $env_output .= '<p>📌 Расширение ' . $ext . ': <span style="color:#EF4444">✗ Не загружено</span></p>';
        $env_ok = false;
    }
}

// Права на запись
$dirs_to_check = [
    __DIR__ . '/public_html/uploads',
    __DIR__ . '/logs',
    __DIR__ . '/app/config'
];

foreach ($dirs_to_check as $dir) {
    if (is_writable_path($dir)) {
        $env_output .= '<p>📌 Права на ' . basename($dir) . ': <span style="color:#10B981">✓ Доступно</span></p>';
    } else {
        $env_output .= '<p>📌 Права на ' . basename($dir) . ': <span style="color:#EF4444">✗ Нет прав на запись</span></p>';
        $env_ok = false;
    }
}

// Если окружение не подходит, показываем ошибку и прекращаем
if (!$env_ok) {
    echo '<!DOCTYPE html><html><head><title>Ошибка окружения</title><style>body{font-family:sans-serif;background:#0B3B5C;color:white;padding:40px;}.error{background:#FEE2E2;color:#991B1B;padding:20px;border-radius:8px;}</style></head><body>';
    echo '<div class="error"><h2>❌ Окружение не соответствует требованиям</h2>' . $env_output . '</div>';
    echo '</body></html>';
    exit;
}

// ШАГ 2: Получение параметров подключения к БД
$step = 1;
$db_config = $_SESSION['db_config'] ?? [];

// Если параметры уже есть в сессии, используем их
if (!empty($db_config) && isset($db_config['host'], $db_config['name'], $db_config['user'], $db_config['pass'])) {
    $step = 2;
} else {
    // Обработка отправки формы с параметрами БД
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_step'])) {
        $db_config = [
            'host' => trim($_POST['db_host']),
            'name' => trim($_POST['db_name']),
            'user' => trim($_POST['db_user']),
            'pass' => $_POST['db_pass']
        ];

        // Проверяем подключение
        try {
            $pdo = new PDO(
                "mysql:host={$db_config['host']}",
                $db_config['user'],
                $db_config['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Проверяем существование базы данных
            $stmt = $pdo->query("SHOW DATABASES LIKE '{$db_config['name']}'");
            $db_exists = $stmt->rowCount() > 0;

            if (!$db_exists) {
                // База не существует – предлагаем создать
                $_SESSION['db_config'] = $db_config;
                $_SESSION['db_check'] = ['exists' => false, 'pdo' => $pdo];
                $step = 'create_db';
            } else {
                // База существует – используем её
                $_SESSION['db_config'] = $db_config;
                $step = 2;
            }
        } catch (PDOException $e) {
            $db_error = 'Ошибка подключения: ' . $e->getMessage();
        }
    } elseif (isset($_POST['create_db'])) {
        // Создание базы данных
        $db_config = $_SESSION['db_config'];
        $pdo = $_SESSION['db_check']['pdo'];

        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_config['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            unset($_SESSION['db_check']);
            $step = 2;
        } catch (PDOException $e) {
            $db_error = 'Ошибка создания БД: ' . $e->getMessage();
        }
    }
}

// Если дошли до шага 2, начинаем установку
if ($step === 2) {
    $db_config = $_SESSION['db_config'];

    try {
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4",
            $db_config['user'],
            $db_config['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        die('❌ Не удалось подключиться к базе данных: ' . $e->getMessage());
    }

    // ШАГ 3: Создание таблиц
    $tables_created = 0;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(100) NOT NULL UNIQUE,
            `value` LONGTEXT,
            `type` ENUM('text','textarea','image','phone','email','url','color') DEFAULT 'text',
            `group` VARCHAR(50) DEFAULT 'general',
            `label` VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_group (`group`),
            INDEX idx_key (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $tables_created++;

        $pdo->exec("CREATE TABLE IF NOT EXISTS gallery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255),
            filepath VARCHAR(500) NOT NULL,
            thumbnail VARCHAR(500),
            alt_text VARCHAR(255),
            title VARCHAR(255),
            description TEXT,
            filesize INT,
            mime_type VARCHAR(100),
            width INT,
            height INT,
            sort_order INT DEFAULT 0,
            is_published TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_sort (sort_order),
            INDEX idx_published (is_published)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $tables_created++;

        $pdo->exec("CREATE TABLE IF NOT EXISTS services (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE,
            description TEXT,
            full_description LONGTEXT,
            icon VARCHAR(100),
            image_id INT,
            price VARCHAR(100),
            features TEXT,
            sort_order INT DEFAULT 0,
            is_published TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (image_id) REFERENCES gallery(id) ON DELETE SET NULL,
            INDEX idx_sort (sort_order),
            INDEX idx_published (is_published)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $tables_created++;

        $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE,
            client VARCHAR(255),
            location VARCHAR(255),
            depth VARCHAR(100),
            description TEXT,
            content LONGTEXT,
            cover_image_id INT,
            completion_date DATE,
            sort_order INT DEFAULT 0,
            is_published TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (cover_image_id) REFERENCES gallery(id) ON DELETE SET NULL,
            INDEX idx_sort (sort_order),
            INDEX idx_published (is_published)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $tables_created++;

        $pdo->exec("CREATE TABLE IF NOT EXISTS projects_gallery (
            project_id INT NOT NULL,
            gallery_id INT NOT NULL,
            sort_order INT DEFAULT 0,
            PRIMARY KEY (project_id, gallery_id),
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (gallery_id) REFERENCES gallery(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $tables_created++;

        $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            author VARCHAR(255) NOT NULL,
            avatar_id INT,
            rating INT DEFAULT 5,
            text TEXT NOT NULL,
            project_id INT,
            project_title VARCHAR(255),
            is_published TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (avatar_id) REFERENCES gallery(id) ON DELETE SET NULL,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
            INDEX idx_published (is_published)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $tables_created++;

        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(255),
            avatar_id INT,
            role ENUM('admin', 'editor') DEFAULT 'editor',
            is_active TINYINT(1) DEFAULT 1,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (avatar_id) REFERENCES gallery(id) ON DELETE SET NULL,
            INDEX idx_username (username),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $tables_created++;

        $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            address VARCHAR(500),
            work_description TEXT,
            status ENUM('new','in_progress','completed','rejected') DEFAULT 'new',
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $tables_created++;

    } catch (PDOException $e) {
        $install_error = 'Ошибка создания таблиц: ' . $e->getMessage();
    }

    // ШАГ 4: Загрузка демо-данных (если нет ошибок)
    if (!isset($install_error)) {
        try {
            // Настройки сайта
            $settings_data = [
                ['site_name', 'БурСервис', 'text', 'general', 'Название компании'],
                ['show_logo_text', '1', 'text', 'general', 'Показывать название компании рядом с логотипом'],
                ['site_logo', '/assets/img/logo.png', 'image', 'general', 'Логотип'],
                ['site_favicon', '/assets/img/favicon.png', 'image', 'general', 'Favicon'],
                ['site_description', 'Профессиональное бурение скважин на воду. Работаем по всей области.', 'textarea', 'seo', 'Meta-описание'],
                ['site_keywords', 'бурение скважин, вода на участке, скважина под ключ', 'text', 'seo', 'Ключевые слова'],
                ['hero_title', 'Бурение скважин на воду', 'text', 'home', 'Заголовок главного экрана'],
                ['hero_subtitle', 'от 1500 руб/метр', 'text', 'home', 'Подзаголовок'],
                ['hero_description', 'Быстро, качественно, с гарантией до 5 лет', 'textarea', 'home', 'Описание'],
                ['hero_button_text', 'Заказать звонок', 'text', 'home', 'Текст кнопки'],
                ['hero_button_link', '#contact', 'url', 'home', 'Ссылка кнопки'],
                ['services_subtitle', 'Что мы предлагаем', 'text', 'services', 'Подзаголовок блока услуг'],
                ['services_title', 'Наши услуги по бурению', 'text', 'services', 'Заголовок блока услуг'],
                ['about_subtitle', 'О нас', 'text', 'about', 'Подзаголовок'],
                ['about_title', 'Компания БурСервис', 'text', 'about', 'Заголовок'],
                ['about_text', '<p>Мы работаем с 2010 года. За это время пробурили более 500 скважин.</p><p>Используем современное оборудование и даем гарантию на все работы.</p>', 'textarea', 'about', 'Текст'],
                ['about_image', '/assets/img/about.jpg', 'image', 'about', 'Изображение'],
                ['projects_subtitle', 'Наши работы', 'text', 'projects', 'Подзаголовок'],
                ['projects_title', 'Выполненные проекты', 'text', 'projects', 'Заголовок'],
                ['reviews_subtitle', 'Отзывы', 'text', 'reviews', 'Подзаголовок'],
                ['reviews_title', 'Что говорят клиенты', 'text', 'reviews', 'Заголовок'],
                ['contact_subtitle', 'Свяжитесь с нами', 'text', 'contacts', 'Подзаголовок'],
                ['contact_title', 'Наши контакты', 'text', 'contacts', 'Заголовок'],
                ['contact_phone', '+7 (999) 123-45-67', 'phone', 'contacts', 'Основной телефон'],
                ['contact_phone_2', '+7 (999) 765-43-21', 'phone', 'contacts', 'Дополнительный телефон'],
                ['contact_email', 'info@burservice.ru', 'email', 'contacts', 'Email'],
                ['contact_address', 'г. Москва, ул. Строителей, д. 15', 'text', 'contacts', 'Адрес'],
                ['work_hours', 'Пн-Пт: 9:00-18:00, Сб: 10:00-16:00', 'text', 'contacts', 'Режим работы'],
                ['social_vk', 'https://vk.com/burservice', 'url', 'social', 'ВКонтакте'],
                ['social_telegram', 'https://t.me/burservice', 'url', 'social', 'Telegram'],
                ['social_whatsapp', 'https://wa.me/79991234567', 'url', 'social', 'WhatsApp'],
                ['form_title', 'Оставить заявку', 'text', 'form', 'Заголовок формы'],
                ['form_subtitle', 'Заполните форму и мы свяжемся с вами', 'text', 'form', 'Подзаголовок формы'],
                ['telegram_bot_token', '', 'text', 'telegram', 'Токен Telegram бота'],
                ['telegram_chat_id', '', 'text', 'telegram', 'Chat ID для уведомлений'],
                ['footer_text', '© 2024 БурСервис. Все права защищены.', 'text', 'footer', 'Текст в подвале'],
                ['footer_description', 'Профессиональное бурение скважин любой сложности', 'textarea', 'footer', 'Описание в подвале']
            ];

            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`, `type`, `group`, `label`) VALUES (?, ?, ?, ?, ?)");
            $settings_count = 0;
            foreach ($settings_data as $setting) {
                try {
                    $stmt->execute($setting);
                    $settings_count++;
                } catch (PDOException $e) {
                    // пропускаем дубликаты
                }
            }

            // Услуги
            $services_data = [
                ['Бурение на воду', 'Бурение скважин на песок и известняк', 'fas fa-water', 'от 1800 руб/м', '["Гарантия 3 года", "Обсадная труба", "Фильтр в комплекте"]', 1],
                ['Монтаж насоса', 'Установка и подключение погружного насоса', 'fas fa-tools', 'от 5000 руб', '["Любые модели", "Автоматика", "Пусконаладка"]', 2],
                ['Обслуживание скважин', 'Чистка и ремонт существующих скважин', 'fas fa-wrench', 'от 3000 руб', '["Диагностика", "Промывка", "Замена фильтров"]', 3],
                ['Горизонтальное бурение', 'Бурение под коммуникации', 'fas fa-arrows-alt-h', 'от 2500 руб/м', '["Без траншей", "Любая длина", "Быстрый монтаж"]', 4],
                ['Геологоразведка', 'Исследование грунта', 'fas fa-flask', 'от 5000 руб', '["Анализ почвы", "Определение глубины", "Рекомендации"]', 5]
            ];
            $stmt = $pdo->prepare("INSERT INTO services (title, description, icon, price, features, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $services_count = 0;
            foreach ($services_data as $service) {
                try {
                    $stmt->execute($service);
                    $services_count++;
                } catch (PDOException $e) {
                    // пропускаем
                }
            }

            // Пользователи
            $users_data = [
                ['admin', 'admin@burservice.ru', password_hash('admin123', PASSWORD_DEFAULT), 'Главный администратор', 'admin'],
                ['editor', 'editor@burservice.ru', password_hash('editor123', PASSWORD_DEFAULT), 'Редактор сайта', 'editor']
            ];
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)");
            $users_count = 0;
            foreach ($users_data as $user) {
                try {
                    $stmt->execute($user);
                    $users_count++;
                } catch (PDOException $e) {
                    // пропускаем
                }
            }

        } catch (PDOException $e) {
            $install_error = 'Ошибка загрузки данных: ' . $e->getMessage();
        }
    }

    // ШАГ 5: Создание файла конфигурации
    $config_path = __DIR__ . '/app/config/db.php';
    $config_dir = dirname($config_path);
    if (!file_exists($config_dir)) {
        mkdir($config_dir, 0755, true);
    }

    $config_content = '<?php
// ==============================================
// db.php - Подключение к базе данных
// ==============================================
class Database {
    private $host = \'' . addslashes($db_config['host']) . '\';
    private $dbname = \'' . addslashes($db_config['name']) . '\';
    private $username = \'' . addslashes($db_config['user']) . '\';
    private $password = \'' . addslashes($db_config['pass']) . '\';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            error_log("[DATABASE] Connection error: " . $e->getMessage());
            die("Ошибка подключения к базе данных.");
        }
        return $this->conn;
    }
}';

    if (!file_put_contents($config_path, $config_content)) {
        $install_error = 'Не удалось создать файл конфигурации.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка сайта бурения скважин</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0B3B5C 0%, #1A5F7A 100%);
            color: white;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .installer {
            background: white;
            color: #1E293B;
            max-width: 900px;
            width: 100%;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        h1 { 
            color: #0B3B5C; 
            font-size: 28px; 
            margin-bottom: 30px;
            border-bottom: 2px solid #FAB13B;
            padding-bottom: 15px;
        }
        h2 { 
            color: #0B3B5C; 
            font-size: 20px; 
            margin: 25px 0 15px;
        }
        .step { 
            background: #F8FAFC; 
            border-radius: 12px; 
            padding: 20px; 
            margin-bottom: 15px;
            border-left: 4px solid #FAB13B;
        }
        .success { 
            background: #D1FAE5; 
            color: #065F46; 
            padding: 15px; 
            border-radius: 8px; 
            margin: 10px 0;
        }
        .error { 
            background: #FEE2E2; 
            color: #991B1B; 
            padding: 15px; 
            border-radius: 8px; 
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            background: #0B3B5C;
            color: white;
            padding: 14px 28px;
            border-radius: 8px;
            text-decoration: none;
            margin: 10px 5px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover { 
            background: #072A44; 
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .btn-warning {
            background: #FAB13B;
            color: #1E293B;
        }
        .btn-warning:hover {
            background: #E0911C;
        }
        .info {
            background: #EFF6FF;
            color: #1E40AF;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        code {
            background: #E2E8F0;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #E2E8F0;
        }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #E2E8F0;
            border-radius: 4px;
            margin: 20px 0;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #10B981;
            transition: width 0.3s;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #0B3B5C;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="installer">
        <h1>🚀 Установка сайта бурения скважин</h1>

        <?php if ($step === 1): ?>
            <!-- Шаг 1: форма ввода параметров БД -->
            <div class="step">
                <h2>🔧 Параметры подключения к базе данных</h2>
                <?php if (isset($db_error)): ?>
                    <div class="error"><?= htmlspecialchars($db_error) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="db_step" value="1">
                    <div class="form-group">
                        <label>Хост MySQL (обычно localhost)</label>
                        <input type="text" name="db_host" class="form-control" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label>Имя базы данных</label>
                        <input type="text" name="db_name" class="form-control" placeholder="например, drilling_site" required>
                    </div>
                    <div class="form-group">
                        <label>Пользователь MySQL</label>
                        <input type="text" name="db_user" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Пароль MySQL</label>
                        <input type="password" name="db_pass" class="form-control">
                    </div>
                    <button type="submit" class="btn">Проверить подключение</button>
                </form>
            </div>

        <?php elseif ($step === 'create_db'): ?>
            <!-- База не существует, предлагаем создать -->
            <div class="step">
                <h2>🗄️ База данных не найдена</h2>
                <p>База данных <strong><?= htmlspecialchars($_SESSION['db_config']['name']) ?></strong> не существует. Хотите создать её?</p>
                <form method="POST">
                    <button type="submit" name="create_db" class="btn btn-warning">Создать базу данных</button>
                    <a href="?reset=1" class="btn" style="background:#EF4444;">Ввести другие параметры</a>
                </form>
            </div>

        <?php elseif ($step === 2): ?>
            <!-- Установка -->
            <div class="step">
                <h2>📊 ШАГ 3: Создание таблиц</h2>
                <?php if (isset($install_error)): ?>
                    <div class="error">❌ <?= htmlspecialchars($install_error) ?></div>
                <?php else: ?>
                    <div class="success">✅ Создано <?= $tables_created ?> таблиц</div>
                <?php endif; ?>
            </div>

            <?php if (!isset($install_error)): ?>
            <div class="step">
                <h2>📥 ШАГ 4: Загрузка демо-данных</h2>
                <div class="success">✅ Загружено <?= $settings_count ?? 0 ?> настроек</div>
                <div class="success">✅ Загружено <?= $services_count ?? 0 ?> услуг</div>
                <div class="success">✅ Загружено <?= $users_count ?? 0 ?> пользователей</div>
            </div>

            <div class="step">
                <h2>⚙️ ШАГ 5: Создание файла конфигурации</h2>
                <div class="success">✅ Файл конфигурации создан</div>
            </div>

            <div class="step">
                <h2>📁 ШАГ 6: Создание директорий</h2>
                <?php
                $dirs = [
                    __DIR__ . '/public_html/uploads/images',
                    __DIR__ . '/public_html/uploads/thumbnails',
                    __DIR__ . '/logs',
                ];
                foreach ($dirs as $dir) {
                    if (!file_exists($dir)) mkdir($dir, 0777, true);
                }
                ?>
                <div class="success">✅ Директории созданы</div>
            </div>

            <div class="success" style="margin-top: 30px; padding: 30px;">
                <h2 style="color: #065F46; margin-bottom: 20px;">🎉 УСТАНОВКА УСПЕШНО ЗАВЕРШЕНА!</h2>
                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <a href="/public_html/" class="btn" target="_blank">🌐 Перейти на сайт</a>
                    <a href="/public_html/admin/" class="btn btn-warning" target="_blank">👤 Войти в админку</a>
                    <a href="?reset=1" class="btn" style="background: #EF4444;" onclick="return confirm('Вы уверены? Это удалит все данные!')">⚠️ Сбросить установку</a>
                </div>
                <div style="margin-top: 25px; background: #F8FAFC; padding: 20px; border-radius: 10px;">
                    <h3 style="color: #0B3B5C; margin-bottom: 15px;">🔑 Данные для входа:</h3>
                    <table>
                        <tr><td><strong>Администратор:</strong></td><td>admin</td><td>admin123</td></tr>
                        <tr><td><strong>Редактор:</strong></td><td>editor</td><td>editor123</td></tr>
                    </table>
                    <p style="color: #EF4444; margin-top: 15px;"><strong>⚠️ Важно:</strong> Удалите файл install.php после установки!</p>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>