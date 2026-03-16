<?php
// ==============================================
// index.php - Главная страница (финальная версия)
// ==============================================
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/models/Settings.php';
require_once __DIR__ . '/../app/models/Service.php';
require_once __DIR__ . '/../app/models/Project.php';
require_once __DIR__ . '/../app/models/Review.php';
require_once __DIR__ . '/../app/models/Gallery.php';
require_once __DIR__ . '/../app/models/Application.php';

$settings = new Settings();
$serviceModel = new Service();
$projectModel = new Project();
$reviewModel = new Review();
$gallery = new Gallery(); // для получения изображений услуг

// Если БД не инициализирована, показываем заглушку
if(!$settings->isInitialized()) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Установка сайта</title>
        <style>
            body { font-family: sans-serif; text-align: center; padding: 50px; background: #0B3B5C; color: white; }
            a { color: #FAB13B; text-decoration: none; font-weight: bold; }
            .container { max-width: 600px; margin: 0 auto; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🚧 Сайт находится в разработке</h1>
            <p>Для установки базы данных перейдите по ссылке:</p>
            <p><a href="/install.php">→ Запустить установку</a></p>
        </div>
    </body>
    </html>';
    exit;
}

$services = $serviceModel->getAllPublished();
$projects = $projectModel->getLatest(6);
$reviews = $reviewModel->getAllPublished();

$feedback_success = false;
$feedback_error = '';

// Обработка AJAX-запросов из модальных окон
if (isset($_POST['ajax']) && $_POST['ajax'] == '1' && isset($_POST['feedback_form'])) {
    header('Content-Type: application/json');
    
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $work_description = trim($_POST['work_description'] ?? '');
    $is_callback = isset($_POST['callback']) ? true : false;

    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Пожалуйста, заполните имя и телефон']);
        exit;
    }

    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digits) !== 11) {
        echo json_encode(['success' => false, 'message' => 'Введите корректный номер телефона из 11 цифр (например, +7(932)252-00-62)']);
        exit;
    }

    // Сохраняем заявку в БД
    $appModel = new Application();
    $appData = [
        'name' => $name,
        'phone' => $phone,
        'address' => $address,
        'work_description' => $work_description ?: ($is_callback ? 'Обратный звонок' : ''),
        'ip' => $_SERVER['REMOTE_ADDR']
    ];
    $appModel->create($appData);

    // Отправка email
    $type = $is_callback ? 'Обратный звонок' : 'Заявка с сайта';
    $to = $settings->get('contact_email', 'admin@burservice.ru');
    $subject = "Новая заявка: $type";
    $email_message = "Тип: $type\nИмя: $name\nТелефон: $phone\n";
    if (!empty($address)) $email_message .= "Адрес объекта: $address\n";
    if (!empty($work_description)) $email_message .= "Что нужно сделать: $work_description\n";
    $email_message .= "IP: " . $_SERVER['REMOTE_ADDR'];
    mail($to, $subject, $email_message);

    // Отправка в Telegram
    $bot_token = $settings->get('telegram_bot_token');
    $chat_id = $settings->get('telegram_chat_id');
    if ($bot_token && $chat_id) {
        $message_text = "🔔 $type\n\n";
        $message_text .= "👤 Имя: $name\n";
        $message_text .= "📞 Телефон: $phone\n";
        if (!empty($address)) $message_text .= "📍 Адрес: $address\n";
        if (!empty($work_description)) $message_text .= "📋 Описание: $work_description\n";
        $message_text .= "🌐 IP: " . $_SERVER['REMOTE_ADDR'];

        $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $message_text,
            'parse_mode' => 'HTML'
        ];
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        $context = stream_context_create($options);
        @file_get_contents($url, false, $context);
    }

    echo json_encode(['success' => true, 'message' => 'Спасибо! Ваша заявка отправлена.']);
    exit;
}

// Обычная POST-обработка (если JavaScript отключён)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_form']) && !isset($_POST['ajax'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $work_description = trim($_POST['work_description'] ?? '');
    $is_callback = isset($_POST['callback']) ? true : false;

    if (empty($name) || empty($phone)) {
        $feedback_error = 'Пожалуйста, заполните имя и телефон';
    } else {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) !== 11) {
            $feedback_error = 'Введите корректный номер телефона из 11 цифр (например, +7(932)252-00-62)';
        } else {
            $feedback_success = true;

            // Сохраняем заявку в БД
            $appModel = new Application();
            $appData = [
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
                'work_description' => $work_description ?: ($is_callback ? 'Обратный звонок' : ''),
                'ip' => $_SERVER['REMOTE_ADDR']
            ];
            $appModel->create($appData);

            // Формируем сообщение для email
            $type = $is_callback ? 'Обратный звонок' : 'Заявка с сайта';
            $to = $settings->get('contact_email', 'admin@burservice.ru');
            $subject = "Новая заявка: $type";
            $email_message = "Тип: $type\nИмя: $name\nТелефон: $phone\n";
            if (!empty($address)) $email_message .= "Адрес объекта: $address\n";
            if (!empty($work_description)) $email_message .= "Что нужно сделать: $work_description\n";
            $email_message .= "IP: " . $_SERVER['REMOTE_ADDR'];

            mail($to, $subject, $email_message);

            // Отправка в Telegram
            $bot_token = $settings->get('telegram_bot_token');
            $chat_id = $settings->get('telegram_chat_id');
            if ($bot_token && $chat_id) {
                $message_text = "🔔 $type\n\n";
                $message_text .= "👤 Имя: $name\n";
                $message_text .= "📞 Телефон: $phone\n";
                if (!empty($address)) $message_text .= "📍 Адрес: $address\n";
                if (!empty($work_description)) $message_text .= "📋 Описание: $work_description\n";
                $message_text .= "🌐 IP: " . $_SERVER['REMOTE_ADDR'];

                $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
                $data = [
                    'chat_id' => $chat_id,
                    'text' => $message_text,
                    'parse_mode' => 'HTML'
                ];
                $options = [
                    'http' => [
                        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method' => 'POST',
                        'content' => http_build_query($data)
                    ]
                ];
                $context = stream_context_create($options);
                @file_get_contents($url, false, $context);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="description" content="<?= htmlspecialchars($settings->get('site_description', 'Профессиональное бурение скважин')) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($settings->get('site_keywords', 'бурение скважин, вода на участке')) ?>">
    
    <title><?= htmlspecialchars($settings->get('site_name', 'БурСервис')) ?> - <?= htmlspecialchars($settings->get('hero_title', 'Бурение скважин')) ?></title>
    
   

    <link rel="icon" type="image/png" href="<?= htmlspecialchars($settings->get('site_favicon', '/assets/img/favicon.png')) ?>">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/css/responsive.css?v=<?= time() ?>">
    <style>
        /* Дополнительные стили для карточек услуг */
        .service-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            transition: all 0.3s;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .service-image {
            width: 100%;
            overflow: hidden;
        }

        .service-image img {
            width: 100%;
            height: auto;
            display: block;
        }

        .service-icon-wrapper {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 24px 0 0 24px;
        }

        .service-icon-wrapper i {
            font-size: 1.75rem;
            color: white;
        }

        .service-content {
            padding: 24px;
        }

        .service-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .service-description {
            color: var(--text-light);
            margin-bottom: 20px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .service-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid var(--bg-gray);
            padding-top: 16px;
        }

        .service-price {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--primary);
        }

        .btn-service-order {
            background: none;
            border: none;
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .btn-service-order:hover {
            color: var(--secondary);
            gap: 8px;
        }

        /* Центрирование логотипа на мобильных */
        @media (max-width: 639px) {
            .logo.logo-centered {
                width: 100%;
                text-align: center;
            }
            .logo.logo-centered .logo-link {
                justify-content: center;
            }
            .service-content {
                padding: 20px;
            }
            .service-icon-wrapper {
                margin: 20px 0 0 20px;
            }
        }
    </style>
</head>
<body>
    <div class="preloader" id="preloader">
        <div class="preloader-spinner"></div>
    </div>

    <button class="scroll-top" id="scrollTop">
        <i class="fas fa-arrow-up"></i>
    </button>

    <header class="header" id="header">
        <div class="container">
            <div class="header-wrapper">
                <div class="logo <?= $settings->get('show_logo_text', '1') != '1' ? 'logo-centered' : '' ?>">
                    <a href="/" class="logo-link">
                        <img src="<?= htmlspecialchars($settings->get('site_logo', '/assets/img/logo.png')) ?>" 
                             alt="<?= htmlspecialchars($settings->get('site_name', 'БурСервис')) ?>" class="logo-image">
                        <?php if ($settings->get('show_logo_text', '1') == '1'): ?>
                            <span class="logo-text"><?= htmlspecialchars($settings->get('site_name', 'БурСервис')) ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <nav class="nav-menu" id="navMenu">
                    <ul class="nav-list">
                        <li><a href="#hero" class="nav-link active">Главная</a></li>
                        <li><a href="#services" class="nav-link">Услуги</a></li>
                        <li><a href="#about" class="nav-link">О нас</a></li>
                        <li><a href="#projects" class="nav-link">Проекты</a></li>
                        <li><a href="#reviews" class="nav-link">Отзывы</a></li>
                        <li><a href="#contact" class="nav-link">Контакты</a></li>
                    </ul>
                </nav>
                
                <div class="header-contacts">
                    <!-- Иконка телефона для мобильных -->
                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $settings->get('contact_phone', '+79991234567')) ?>" class="mobile-phone-icon">
                        <i class="fas fa-phone-alt"></i>
                    </a>

                    <!-- Полный блок с телефоном и режимом работы (на планшетах и выше) -->
                    <div class="header-phone-wrapper">
                        <a href="tel:<?= preg_replace('/[^0-9+]/', '', $settings->get('contact_phone', '+79991234567')) ?>" class="header-phone">
                            <i class="fas fa-phone-alt"></i>
                            <span><?= htmlspecialchars($settings->get('contact_phone', '+7 (999) 123-45-67')) ?></span>
                        </a>
                        <div class="header-work-hours">
                            <?= htmlspecialchars($settings->get('work_hours', 'Пн-Пт: 9:00-18:00')) ?>
                        </div>
                    </div>

                    <!-- Социальные сети (на планшетах и выше) -->
                    <div class="header-social">
                        <?php if ($settings->get('social_vk')): ?>
                            <a href="<?= htmlspecialchars($settings->get('social_vk')) ?>" target="_blank" class="social-icon vk" title="ВКонтакте">
                                <i class="fab fa-vk"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($settings->get('social_telegram')): ?>
                            <a href="<?= htmlspecialchars($settings->get('social_telegram')) ?>" target="_blank" class="social-icon telegram" title="Telegram">
                                <i class="fab fa-telegram"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($settings->get('social_whatsapp')): ?>
                            <a href="<?= htmlspecialchars($settings->get('social_whatsapp')) ?>" target="_blank" class="social-icon whatsapp" title="WhatsApp">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Кнопка обратного звонка (на планшетах и выше) -->
                    <button class="btn-callback" onclick="openCallbackModal()">Обратный звонок</button>

                    <!-- Кнопка мобильного меню -->
                    <button class="mobile-menu-toggle" id="mobileMenuToggle">
                        <span></span><span></span><span></span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Мобильное меню -->
        <div class="mobile-menu" id="mobileMenu">
            <div class="mobile-menu-header">
                <div class="mobile-logo">
                    <img src="<?= htmlspecialchars($settings->get('site_logo', '/assets/img/logo.png')) ?>" 
                         alt="<?= htmlspecialchars($settings->get('site_name', 'БурСервис')) ?>">
                    <?php if ($settings->get('show_logo_text', '1') == '1'): ?>
                        <span><?= htmlspecialchars($settings->get('site_name', 'БурСервис')) ?></span>
                    <?php endif; ?>
                </div>
                <button class="mobile-menu-close" id="mobileMenuClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <ul class="mobile-nav-list">
                <li><a href="#hero" class="mobile-nav-link">Главная</a></li>
                <li><a href="#services" class="mobile-nav-link">Услуги</a></li>
                <li><a href="#about" class="mobile-nav-link">О нас</a></li>
                <li><a href="#projects" class="mobile-nav-link">Проекты</a></li>
                <li><a href="#reviews" class="mobile-nav-link">Отзывы</a></li>
                <li><a href="#contact" class="mobile-nav-link">Контакты</a></li>
            </ul>
            <div class="mobile-contacts">
                <a href="tel:<?= preg_replace('/[^0-9+]/', '', $settings->get('contact_phone', '+79991234567')) ?>" class="mobile-phone">
                    <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($settings->get('contact_phone', '+7 (999) 123-45-67')) ?>
                </a>
                <div class="mobile-work-hours">
                    <i class="far fa-clock"></i> <?= htmlspecialchars($settings->get('work_hours', 'Пн-Пт: 9:00-18:00')) ?>
                </div>
                <a href="mailto:<?= htmlspecialchars($settings->get('contact_email', 'info@burservice.ru')) ?>" class="mobile-email">
                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($settings->get('contact_email', 'info@burservice.ru')) ?>
                </a>
                <!-- Соцсети в мобильном меню -->
                <div class="mobile-social">
                    <?php if ($settings->get('social_vk')): ?>
                        <a href="<?= htmlspecialchars($settings->get('social_vk')) ?>" target="_blank"><i class="fab fa-vk"></i></a>
                    <?php endif; ?>
                    <?php if ($settings->get('social_telegram')): ?>
                        <a href="<?= htmlspecialchars($settings->get('social_telegram')) ?>" target="_blank"><i class="fab fa-telegram"></i></a>
                    <?php endif; ?>
                    <?php if ($settings->get('social_whatsapp')): ?>
                        <a href="<?= htmlspecialchars($settings->get('social_whatsapp')) ?>" target="_blank"><i class="fab fa-whatsapp"></i></a>
                    <?php endif; ?>
                </div>
                <button class="btn-callback mobile-callback" onclick="openCallbackModal(); closeMobileMenu();">Обратный звонок</button>
            </div>
        </div>
        <div class="overlay" id="overlay"></div>
    </header>

    <main>
        <section class="hero" id="hero">
            <div class="container">
                <div class="hero-grid">
                    <div class="hero-content">
                        <h1 class="hero-title"><?= htmlspecialchars($settings->get('hero_title', 'Бурение скважин на воду')) ?></h1>
                        <div class="hero-subtitle"><?= htmlspecialchars($settings->get('hero_subtitle', 'от 1500 руб/метр')) ?></div>
                        <p class="hero-description"><?= nl2br(htmlspecialchars($settings->get('hero_description', 'Быстро, качественно, с гарантией до 5 лет'))) ?></p>
                        <div class="hero-buttons">
                            <button onclick="openCallbackModal()" class="btn btn-primary btn-large">
                                <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($settings->get('hero_button_text', 'Заказать звонок')) ?>
                            </button>
                        </div>
                    </div>
                    <div class="hero-image">
                        <img src="/assets/img/hero-drill.png" alt="Бурение скважины">
                    </div>
                </div>
            </div>
        </section>

        <section class="services" id="services">
            <div class="container">
                <div class="section-header">
                    <span class="section-subtitle"><?= htmlspecialchars($settings->get('services_subtitle', 'Что мы предлагаем')) ?></span>
                    <h2 class="section-title"><?= htmlspecialchars($settings->get('services_title', 'Наши услуги')) ?></h2>
                </div>
                
                <div class="services-grid">
                    <?php if(!empty($services)): ?>
                        <?php foreach($services as $service): 
                            $service_image = null;
                            if (!empty($service['image_id'])) {
                                $service_image = $gallery->getById($service['image_id']);
                            }
                        ?>
                        <div class="service-card">
                            <?php if ($service_image): ?>
                                <div class="service-image">
                                    <img src="<?= htmlspecialchars($service_image['filepath']) ?>" 
                                         alt="<?= htmlspecialchars($service['title']) ?>">
                                </div>
                            <?php else: ?>
                                <div class="service-icon-wrapper">
                                    <i class="<?= htmlspecialchars($service['icon'] ?? 'fas fa-water') ?>"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="service-content">
                                <h3 class="service-title"><?= htmlspecialchars($service['title']) ?></h3>
                                <p class="service-description"><?= htmlspecialchars($service['description']) ?></p>
                                
                                <div class="service-footer">
                                    <span class="service-price"><?= htmlspecialchars($service['price']) ?></span>
                                    <button class="btn-service-order" onclick="openOrderModal('<?= htmlspecialchars($service['title']) ?>')">
                                        Заказать <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Демо-услуги (с такой же структурой) -->
                        <div class="service-card">
                            <div class="service-icon-wrapper"><i class="fas fa-water"></i></div>
                            <div class="service-content">
                                <h3 class="service-title">Бурение на воду</h3>
                                <p class="service-description">Бурение скважин на песок и известняк</p>
                                <div class="service-footer">
                                    <span class="service-price">от 1800 руб/м</span>
                                    <button class="btn-service-order" onclick="openOrderModal('Бурение на воду')">Заказать <i class="fas fa-arrow-right"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="service-card">
                            <div class="service-icon-wrapper"><i class="fas fa-tools"></i></div>
                            <div class="service-content">
                                <h3 class="service-title">Монтаж насоса</h3>
                                <p class="service-description">Установка и подключение погружного насоса</p>
                                <div class="service-footer">
                                    <span class="service-price">от 5000 руб</span>
                                    <button class="btn-service-order" onclick="openOrderModal('Монтаж насоса')">Заказать <i class="fas fa-arrow-right"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="service-card">
                            <div class="service-icon-wrapper"><i class="fas fa-wrench"></i></div>
                            <div class="service-content">
                                <h3 class="service-title">Обслуживание скважин</h3>
                                <p class="service-description">Чистка и ремонт существующих скважин</p>
                                <div class="service-footer">
                                    <span class="service-price">от 3000 руб</span>
                                    <button class="btn-service-order" onclick="openOrderModal('Обслуживание скважин')">Заказать <i class="fas fa-arrow-right"></i></button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="about" id="about">
            <div class="container">
                <div class="about-grid">
                    <div class="about-content">
                        <span class="section-subtitle"><?= htmlspecialchars($settings->get('about_subtitle', 'О нас')) ?></span>
                        <h2 class="section-title"><?= htmlspecialchars($settings->get('about_title', 'Компания БурСервис')) ?></h2>
                        <div class="about-text">
                            <?= $settings->get('about_text', '<p>Мы работаем с 2010 года. За это время пробурили более 500 скважин.</p>') ?>
                        </div>
                    </div>
                    <div class="about-image">
                        <img src="<?= htmlspecialchars($settings->get('about_image', '/assets/img/about.jpg')) ?>" alt="О компании">
                    </div>
                </div>
            </div>
        </section>

        <section class="contact" id="contact">
            <div class="container">
                <div class="contact-grid">
                    <div class="contact-info">
                        <span class="section-subtitle"><?= htmlspecialchars($settings->get('contact_subtitle', 'Свяжитесь с нами')) ?></span>
                        <h2 class="section-title"><?= htmlspecialchars($settings->get('contact_title', 'Контакты')) ?></h2>
                        
                        <div class="contact-details">
                            <div class="contact-item">
                                <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                                <div class="contact-text">
                                    <h4>Телефон</h4>
                                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $settings->get('contact_phone', '+79991234567')) ?>">
                                        <?= htmlspecialchars($settings->get('contact_phone', '+7 (999) 123-45-67')) ?>
                                    </a>
                                </div>
                            </div>
                            <div class="contact-item">
                                <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                                <div class="contact-text">
                                    <h4>Email</h4>
                                    <a href="mailto:<?= htmlspecialchars($settings->get('contact_email', 'info@burservice.ru')) ?>">
                                        <?= htmlspecialchars($settings->get('contact_email', 'info@burservice.ru')) ?>
                                    </a>
                                </div>
                            </div>
                            <div class="contact-item">
                                <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="contact-text">
                                    <h4>Адрес</h4>
                                    <p><?= nl2br(htmlspecialchars($settings->get('contact_address', 'г. Москва, ул. Строителей, д. 15'))) ?></p>
                                </div>
                            </div>
                            <div class="contact-item">
                                <div class="contact-icon"><i class="far fa-clock"></i></div>
                                <div class="contact-text">
                                    <h4>Режим работы</h4>
                                    <p><?= nl2br(htmlspecialchars($settings->get('work_hours', 'Пн-Пт: 9:00-18:00'))) ?></p>
                                </div>
                            </div>
                        </div>


                        <div class="contact-map">
                            <h4>Как нас найти</h4>
                            <script type="text/javascript" charset="utf-8" async src="https://api-maps.yandex.ru/services/constructor/1.0/js/?um=constructor%3A704a06d991ab1b613852fab1c46c53a0f46936703dda6f9c3dbb844987189d29&amp;width=500&amp;height=400&amp;lang=ru_RU&amp;scroll=true">

                            </script>
                         </div>





                    </div>
                    
                    <div class="contact-form-wrapper">
                        <div class="form-card">
                            <h3><?= htmlspecialchars($settings->get('form_title', 'Оставить заявку')) ?></h3>
                            <p><?= htmlspecialchars($settings->get('form_subtitle', 'Заполните форму и мы свяжемся с вами')) ?></p>
                            
                            <?php if($feedback_success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Спасибо! Ваша заявка отправлена.
                            </div>
                            <?php endif; ?>
                            
                            <?php if($feedback_error): ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($feedback_error) ?>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="#contact" class="feedback-form" id="feedbackForm">
                                <input type="hidden" name="feedback_form" value="1">
                                
                                <div class="form-group">
                                    <input type="text" name="name" placeholder="Ваше имя *" required>
                                </div>
                                
                                <div class="form-group">
                                    <input type="tel" name="phone" id="phone" placeholder="+7 (___) ___-__-__ *" required>
                                </div>
                                
                                <div class="form-group">
                                    <input type="text" name="address" placeholder="Адрес объекта (город, улица)">
                                </div>
                                
                                <div class="form-group">
                                    <textarea name="work_description" rows="3" placeholder="Что нужно сделать? (тип бурения, глубина и т.п.)"></textarea>
                                </div>
                                
                                <div class="form-group form-checkbox">
                                    <input type="checkbox" id="agree" name="agree" checked required>
                                    <label for="agree">Согласие на обработку данных</label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-block" id="submitBtn">
                                    <i class="fas fa-paper-plane"></i> Отправить
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="footer-logo">
                        <img src="<?= htmlspecialchars($settings->get('site_logo', '/assets/img/logo.png')) ?>" 
                             alt="<?= htmlspecialchars($settings->get('site_name', 'БурСервис')) ?>">
                        <?php if ($settings->get('show_logo_text', '1') == '1'): ?>
                            <span><?= htmlspecialchars($settings->get('site_name', 'БурСервис')) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="footer-description">
                        <?= htmlspecialchars($settings->get('footer_description', 'Профессиональное бурение скважин')) ?>
                    </p>
                </div>
                <div class="footer-col">
                    <h4>Контакты</h4>
                    <ul class="footer-contact">
                        <li><i class="fas fa-phone"></i> <a href="tel:<?= preg_replace('/[^0-9+]/', '', $settings->get('contact_phone', '+79991234567')) ?>"><?= htmlspecialchars($settings->get('contact_phone', '+7 (999) 123-45-67')) ?></a></li>
                        <li><i class="fas fa-envelope"></i> <a href="mailto:<?= htmlspecialchars($settings->get('contact_email', 'info@burservice.ru')) ?>"><?= htmlspecialchars($settings->get('contact_email', 'info@burservice.ru')) ?></a></li>
                        <li><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($settings->get('contact_address', 'г. Москва, ул. Строителей, д. 15')) ?></li>
                        <li><i class="far fa-clock"></i> <?= htmlspecialchars($settings->get('work_hours', 'Пн-Пт: 9:00-18:00')) ?></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p><?= htmlspecialchars($settings->get('footer_text', '© 2024 БурСервис. Все права защищены.')) ?></p>
            </div>
        </div>
    </footer>

    <!-- Модальное окно заказа услуги (из карточек) -->
    <div class="modal" id="orderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Заказать услугу</h3>
                <button class="modal-close" onclick="closeOrderModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p id="modalServiceText"></p>
                <form method="POST" action="#contact">
                    <input type="hidden" name="feedback_form" value="1">
                    <input type="hidden" name="modal_order" value="1" id="modalOrderInput">
                    <div class="form-group">
                        <input type="text" name="name" placeholder="Ваше имя *" required>
                    </div>
                    <div class="form-group">
                        <input type="tel" name="phone" placeholder="+7 (___) ___-__-__ *" required>
                    </div>
                    <div class="form-group form-checkbox">
                        <input type="checkbox" id="order_agree" name="agree" checked required>
                        <label for="order_agree">Согласие на обработку данных</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Отправить</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Модальное окно обратного звонка (с AJAX) -->
    <div class="modal" id="callbackModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Заказать обратный звонок</h3>
                <button class="modal-close" onclick="closeCallbackModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form class="callback-form" id="callbackForm">
                    <input type="hidden" name="feedback_form" value="1">
                    <input type="hidden" name="callback" value="1">
                    <input type="hidden" name="ajax" value="1">
                    
                    <div class="form-group">
                        <input type="text" name="name" placeholder="Ваше имя *" required>
                    </div>
                    
                    <div class="form-group">
                        <input type="tel" name="phone" placeholder="+7 (___) ___-__-__ *" required>
                    </div>
                    
                    <div class="form-group form-checkbox">
                        <input type="checkbox" name="agree" id="callback_agree" checked required>
                        <label for="callback_agree">Согласие на обработку персональных данных</label>
                    </div>
                    
                    <div class="form-group" id="callbackMessage" style="display: none;"></div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Перезвоните мне</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/assets/js/main.js?v=<?= time() ?>"></script>
    <script>
        // Маска для телефона и валидация
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInputs = document.querySelectorAll('input[type="tel"]');
            phoneInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    if (value.length > 0) {
                        if (value[0] === '8') {
                            value = '7' + value.substring(1);
                        }
                        if (value.length > 0) {
                            value = '+7' + value.substring(1);
                        }
                        
                        let formatted = '';
                        if (value.length > 2) {
                            formatted = value.substring(0, 2) + '(' + value.substring(2, 5);
                        } else {
                            formatted = value;
                        }
                        if (value.length > 5) {
                            formatted += ')' + value.substring(5, 8);
                        }
                        if (value.length > 8) {
                            formatted += '-' + value.substring(8, 10);
                        }
                        if (value.length > 10) {
                            formatted += '-' + value.substring(10, 12);
                        }
                        e.target.value = formatted;
                    } else {
                        e.target.value = '';
                    }
                });
            });

            // Валидация перед отправкой для основной формы
            const feedbackForm = document.getElementById('feedbackForm');
            if (feedbackForm) {
                feedbackForm.addEventListener('submit', function(e) {
                    const phoneInput = document.getElementById('phone');
                    if (!phoneInput) return;
                    const digits = phoneInput.value.replace(/\D/g, '');
                    if (digits.length !== 11) {
                        e.preventDefault();
                        alert('Пожалуйста, введите корректный номер телефона из 11 цифр (например, +7(932)252-00-62)');
                        phoneInput.focus();
                    }
                });
            }
        });

        // AJAX-отправка формы обратного звонка
        document.getElementById('callbackForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var form = this;
            var formData = new FormData(form);
            var msgDiv = document.getElementById('callbackMessage');
            
            // Скрываем предыдущее сообщение
            msgDiv.style.display = 'none';
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        msgDiv.style.display = 'block';
                        
                        if (response.success) {
                            msgDiv.className = 'alert alert-success';
                            form.reset();
                            setTimeout(function() {
                                closeCallbackModal();
                            }, 3000);
                        } else {
                            msgDiv.className = 'alert alert-error';
                        }
                        msgDiv.innerHTML = response.message;
                    } catch (e) {
                        console.error('Ошибка парсинга JSON', e);
                    }
                } else {
                    msgDiv.style.display = 'block';
                    msgDiv.className = 'alert alert-error';
                    msgDiv.innerHTML = 'Произошла ошибка при отправке. Попробуйте позже.';
                }
            };
            
            xhr.onerror = function() {
                msgDiv.style.display = 'block';
                msgDiv.className = 'alert alert-error';
                msgDiv.innerHTML = 'Ошибка соединения. Проверьте интернет.';
            };
            
            xhr.send(formData);
        });

        // Функции для модальных окон
        function openOrderModal(service) {
            document.getElementById('orderModal').classList.add('active');
            document.getElementById('modalServiceText').innerHTML = service ? 
                'Вы выбрали: <strong>' + service + '</strong>' : 
                'Оставьте заявку';
            document.getElementById('modalOrderInput').value = service || '';
            document.body.style.overflow = 'hidden';
        }
        function closeOrderModal() {
            document.getElementById('orderModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function openCallbackModal() {
            document.getElementById('callbackModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeCallbackModal() {
            document.getElementById('callbackModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Закрытие по клику вне модалки
        window.onclick = function(e) {
            const orderModal = document.getElementById('orderModal');
            const callbackModal = document.getElementById('callbackModal');
            if (e.target == orderModal) closeOrderModal();
            if (e.target == callbackModal) closeCallbackModal();
        }

        // Прелоадер
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('preloader').style.opacity = '0';
                setTimeout(function() {
                    document.getElementById('preloader').style.display = 'none';
                }, 500);
            }, 1000);
        });

        // Кнопка наверх
        window.addEventListener('scroll', function() {
            var btn = document.getElementById('scrollTop');
            if(window.scrollY > 300) btn.classList.add('show');
            else btn.classList.remove('show');
        });
        document.getElementById('scrollTop').addEventListener('click', function() {
            window.scrollTo({top: 0, behavior: 'smooth'});
        });

        // Мобильное меню
        var toggle = document.getElementById('mobileMenuToggle');
        var menu = document.getElementById('mobileMenu');
        var close = document.getElementById('mobileMenuClose');
        var overlay = document.getElementById('overlay');

        function closeMobileMenu() {
            menu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        if(toggle) toggle.onclick = function() {
            menu.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        };
        if(close) close.onclick = closeMobileMenu;
        if(overlay) overlay.onclick = closeMobileMenu;

        window.closeMobileMenu = closeMobileMenu;
    </script>
    <script src="assets/js/cookie-banner.js"></script>
</body>
</html>