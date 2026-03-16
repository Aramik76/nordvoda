<?php
// ==============================================
// admin/settings.php - Управление настройками сайта (финальная версия)
// ==============================================
session_start();
ob_start(); // Буферизация вывода

// Разрешаем eval для CKEditor (CSP) – если нужно
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline';");

require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/Auth.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/models/Settings.php';
require_once __DIR__ . '/../../app/models/Gallery.php';

// Функции для flash-сообщений
function setFlash($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function getFlash() {
    $message = $_SESSION['flash_message'] ?? null;
    $type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    return $message ? ['message' => $message, 'type' => $type] : null;
}

$auth = Auth::getInstance();
$auth->requireAdmin(); // Только администратор может изменять настройки

$settings = new Settings();
$gallery = new Gallery();

// Получаем все настройки по группам (для отображения в форме)
$general_settings = $settings->getGroup('general');
$seo_settings = $settings->getGroup('seo');
$home_settings = $settings->getGroup('home');
$about_settings = $settings->getGroup('about');
$contacts_settings = $settings->getGroup('contacts');
$social_settings = $settings->getGroup('social');
$footer_settings = $settings->getGroup('footer');
$form_settings = $settings->getGroup('form');
$telegram_settings = $settings->getGroup('telegram');
$watermark_settings = $settings->getGroup('watermark'); // новая группа

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('Недействительный CSRF токен', 'error');
        ob_clean();
        header('Location: settings.php');
        exit;
    }

    // Загрузка логотипа, если выбран файл
    if (!empty($_FILES['site_logo_file']['name']) && $_FILES['site_logo_file']['error'] == UPLOAD_ERR_OK) {
        $upload_result = $gallery->upload($_FILES['site_logo_file'], 'Логотип сайта', 'Логотип');
        if (!isset($upload_result['error'])) {
            $_POST['setting_site_logo'] = $upload_result['filepath'];
        } else {
            setFlash('Ошибка загрузки логотипа: ' . $upload_result['error'], 'error');
            ob_clean();
            header('Location: settings.php');
            exit;
        }
    }

    // Сохраняем остальные настройки (все поля с префиксом setting_)
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $setting_key = substr($key, 8);
            $settings->set($setting_key, trim($value));
        }
    }

    // Явно сохраняем чекбоксы (могут отсутствовать в POST)
    $settings->set('show_logo_text', isset($_POST['setting_show_logo_text']) ? '1' : '0');
    $settings->set('watermark_scale', isset($_POST['setting_watermark_scale']) ? '1' : '0');
    // Для цвета и текста они уже сохранены в цикле, если были отправлены

    $settings->refresh(); // Обновляем кэш после сохранения

    setFlash('Настройки успешно сохранены!', 'success');
    ob_clean();
    header('Location: settings.php');
    exit;
}

$csrf_token = $auth->generateCsrfToken();
$current_user = $auth->user();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки сайта | Админ-панель</title>
    
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= time() ?>">
    
    <style>
        .settings-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--admin-border);
            padding-bottom: 15px;
        }
        .settings-tab {
            padding: 10px 18px;
            background: white;
            border: 1px solid var(--admin-border);
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.95rem;
            white-space: nowrap;
        }
        .settings-tab.active {
            background: var(--admin-primary);
            color: white;
            border-color: var(--admin-primary);
        }
        .settings-section {
            display: none;
        }
        .settings-section.active {
            display: block;
        }
        .image-preview {
            max-width: 150px;
            max-height: 60px;
            border-radius: 8px;
            border: 1px solid var(--admin-border);
            object-fit: contain;
        }
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }
        .form-checkbox input {
            width: auto;
        }
        @media (max-width: 640px) {
            .settings-tab {
                white-space: normal;
                font-size: 0.85rem;
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>

        <!-- Основной контент -->
        <main class="admin-main">
            <div class="admin-header">
                <div class="admin-title">
                    <h1>Настройки сайта</h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Дашборд</a> / Настройки сайта
                    </div>
                </div>
            </div>
            
            <?php $flash = getFlash(); ?>
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>
            
            <div class="admin-card">
                <div class="admin-card-header">
                    <h2><i class="fas fa-sliders-h"></i> Конфигурация сайта</h2>
                </div>
                <div class="admin-card-body">
                    <!-- Вкладки настроек -->
                    <div class="settings-tabs">
                        <div class="settings-tab active" data-tab="general"><i class="fas fa-globe"></i> Основные</div>
                        <div class="settings-tab" data-tab="seo"><i class="fas fa-search"></i> SEO</div>
                        <div class="settings-tab" data-tab="home"><i class="fas fa-home"></i> Главная</div>
                        <div class="settings-tab" data-tab="about"><i class="fas fa-info-circle"></i> О нас</div>
                        <div class="settings-tab" data-tab="contacts"><i class="fas fa-phone"></i> Контакты</div>
                        <div class="settings-tab" data-tab="social"><i class="fas fa-share-alt"></i> Соцсети</div>
                        <div class="settings-tab" data-tab="footer"><i class="fas fa-copyright"></i> Футер</div>
                        <div class="settings-tab" data-tab="form"><i class="fas fa-envelope"></i> Форма</div>
                        <div class="settings-tab" data-tab="telegram"><i class="fab fa-telegram-plane"></i> Telegram</div>
                        <div class="settings-tab" data-tab="watermark"><i class="fas fa-water"></i> Водяной знак</div>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        
                        <!-- Основные настройки -->
                        <div class="settings-section active" id="tab-general">
                            <h3 style="margin-bottom: 20px;">Основная информация</h3>
                            
                            <div class="form-group">
                                <label>Название компании</label>
                                <input type="text" name="setting_site_name" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('site_name', 'БурСервис')) ?>">
                                <small style="color: var(--admin-gray);">Отображается в шапке сайта и заголовках</small>
                            </div>
                            
                            <div class="form-group form-checkbox">
                                <input type="checkbox" name="setting_show_logo_text" id="show_logo_text" value="1" <?= $settings->get('show_logo_text', '1') == '1' ? 'checked' : '' ?>>
                                <label for="show_logo_text">Показывать название компании рядом с логотипом</label>
                            </div>
                            
                            <div class="form-group">
                                <label>Логотип</label>
                                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                                    <div>
                                        <img src="<?= htmlspecialchars($settings->get('site_logo', '/assets/img/logo.png')) ?>" 
                                             alt="Логотип" class="image-preview" id="logoPreview">
                                    </div>
                                    <div style="flex:1;">
                                        <input type="file" name="site_logo_file" id="site_logo_file" accept="image/*" class="form-control" onchange="previewLogo(this)">
                                        <small style="color: var(--admin-gray);">Загрузите новый логотип (PNG, JPG, SVG). Рекомендуемый размер: высота до 60px.</small>
                                        <input type="hidden" name="setting_site_logo" value="<?= htmlspecialchars($settings->get('site_logo', '/assets/img/logo.png')) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Favicon (URL)</label>
                                <input type="text" name="setting_site_favicon" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('site_favicon', '/assets/img/favicon.png')) ?>">
                            </div>
                        </div>
                        
                        <!-- SEO настройки -->
                        <div class="settings-section" id="tab-seo">
                            <h3 style="margin-bottom: 20px;">SEO оптимизация</h3>
                            
                            <div class="form-group">
                                <label>Meta Description</label>
                                <textarea name="setting_site_description" class="form-control" rows="3"><?= htmlspecialchars($settings->get('site_description', '')) ?></textarea>
                                <small style="color: var(--admin-gray);">Описание сайта для поисковых систем (до 160 символов)</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Meta Keywords</label>
                                <input type="text" name="setting_site_keywords" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('site_keywords', '')) ?>">
                                <small style="color: var(--admin-gray);">Ключевые слова через запятую</small>
                            </div>
                        </div>
                        
                        <!-- Настройки главной страницы -->
                        <div class="settings-section" id="tab-home">
                            <h3 style="margin-bottom: 20px;">Главный экран (Hero)</h3>
                            
                            <div class="form-group">
                                <label>Заголовок</label>
                                <input type="text" name="setting_hero_title" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('hero_title', 'Бурение скважин на воду')) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Подзаголовок</label>
                                <input type="text" name="setting_hero_subtitle" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('hero_subtitle', 'от 1500 руб/метр')) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Описание</label>
                                <textarea name="setting_hero_description" class="form-control" rows="3"><?= htmlspecialchars($settings->get('hero_description', '')) ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Текст кнопки</label>
                                <input type="text" name="setting_hero_button_text" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('hero_button_text', 'Заказать звонок')) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Ссылка кнопки</label>
                                <input type="text" name="setting_hero_button_link" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('hero_button_link', '#contact')) ?>">
                            </div>
                            
                            <h3 style="margin: 30px 0 20px;">Блок услуг</h3>
                            
                            <div class="form-group">
                                <label>Подзаголовок</label>
                                <input type="text" name="setting_services_subtitle" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('services_subtitle', 'Что мы предлагаем')) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Заголовок</label>
                                <input type="text" name="setting_services_title" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('services_title', 'Наши услуги')) ?>">
                            </div>
                        </div>
                        
                        <!-- О компании -->
                        <div class="settings-section" id="tab-about">
                            <h3 style="margin-bottom: 20px;">О компании</h3>
                            
                            <div class="form-group">
                                <label>Заголовок</label>
                                <input type="text" name="setting_about_title" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('about_title', 'О компании')) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Текст</label>
                                <textarea name="setting_about_text" class="form-control" rows="6"><?= htmlspecialchars($settings->get('about_text', '')) ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Изображение (URL)</label>
                                <input type="text" name="setting_about_image" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('about_image', '/assets/img/about.jpg')) ?>">
                                <img src="<?= htmlspecialchars($settings->get('about_image', '/assets/img/about.jpg')) ?>" 
                                     alt="О компании" class="image-preview" onerror="this.style.display='none'">
                            </div>
                        </div>
                        
                        <!-- Контакты -->
                        <div class="settings-section" id="tab-contacts">
                            <h3 style="margin-bottom: 20px;">Контактная информация</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Телефон (основной)</label>
                                    <input type="text" name="setting_contact_phone" class="form-control" 
                                           value="<?= htmlspecialchars($settings->get('contact_phone', '+7 (999) 123-45-67')) ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Телефон (дополнительный)</label>
                                    <input type="text" name="setting_contact_phone_2" class="form-control" 
                                           value="<?= htmlspecialchars($settings->get('contact_phone_2', '')) ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="setting_contact_email" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('contact_email', 'info@burservice.ru')) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Адрес</label>
                                <textarea name="setting_contact_address" class="form-control" rows="2"><?= htmlspecialchars($settings->get('contact_address', '')) ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Режим работы</label>
                                <input type="text" name="setting_work_hours" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('work_hours', 'Пн-Пт: 9:00-18:00')) ?>">
                            </div>
                        </div>
                        
                        <!-- Социальные сети -->
                        <div class="settings-section" id="tab-social">
                            <h3 style="margin-bottom: 20px;">Социальные сети</h3>
                            
                            <div class="form-group">
                                <label><i class="fab fa-vk" style="color: #4C75A3;"></i> ВКонтакте</label>
                                <input type="url" name="setting_social_vk" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('social_vk', '')) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fab fa-telegram" style="color: #0088CC;"></i> Telegram</label>
                                <input type="url" name="setting_social_telegram" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('social_telegram', '')) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fab fa-whatsapp" style="color: #25D366;"></i> WhatsApp</label>
                                <input type="url" name="setting_social_whatsapp" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('social_whatsapp', '')) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fab fa-instagram" style="color: #E4405F;"></i> Instagram</label>
                                <input type="url" name="setting_social_instagram" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('social_instagram', '')) ?>">
                            </div>
                        </div>
                        
                        <!-- Футер -->
                        <div class="settings-section" id="tab-footer">
                            <h3 style="margin-bottom: 20px;">Подвал сайта (Футер)</h3>
                            
                            <div class="form-group">
                                <label>Текст копирайта</label>
                                <input type="text" name="setting_footer_text" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('footer_text', '© 2024 БурСервис. Все права защищены.')) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Описание</label>
                                <textarea name="setting_footer_description" class="form-control" rows="3"><?= htmlspecialchars($settings->get('footer_description', '')) ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Форма обратной связи -->
                        <div class="settings-section" id="tab-form">
                            <h3 style="margin-bottom: 20px;">Форма обратной связи</h3>
                            
                            <div class="form-group">
                                <label>Заголовок формы</label>
                                <input type="text" name="setting_form_title" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('form_title', 'Оставить заявку')) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Подзаголовок</label>
                                <input type="text" name="setting_form_subtitle" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('form_subtitle', 'Заполните форму и мы свяжемся с вами')) ?>">
                            </div>
                        </div>
                        
                        <!-- Telegram -->
                        <div class="settings-section" id="tab-telegram">
                            <h3 style="margin-bottom: 20px;">Настройки Telegram уведомлений</h3>
                            <div class="form-group">
                                <label>Токен бота</label>
                                <input type="text" name="setting_telegram_bot_token" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('telegram_bot_token', '')) ?>">
                                <small style="color: var(--admin-gray);">Получите у @BotFather</small>
                            </div>
                            <div class="form-group">
                                <label>Chat ID</label>
                                <input type="text" name="setting_telegram_chat_id" class="form-control" 
                                       value="<?= htmlspecialchars($settings->get('telegram_chat_id', '')) ?>">
                                <small style="color: var(--admin-gray);">ID чата или группы для отправки уведомлений</small>
                            </div>
                        </div>
                        
                        <!-- Водяной знак -->
                        <div class="settings-section" id="tab-watermark">
                            <h3 style="margin-bottom: 20px;">Настройки водяного знака</h3>
                            <p style="margin-bottom: 15px; color: var(--admin-gray);">Водяной знак накладывается из файла логотипа (указан в основных настройках).</p>

                            <div class="form-group">
                                <label>Действие</label>
                                <select name="setting_watermark_action" class="form-control">
                                    <option value="copy" <?= $settings->get('watermark_action', 'copy') == 'copy' ? 'selected' : '' ?>>Создавать копию с водяным знаком</option>
                                    <option value="replace" <?= $settings->get('watermark_action', 'copy') == 'replace' ? 'selected' : '' ?>>Заменять оригинал (не рекомендуется)</option>
                                </select>
                            </div>

                            <div class="form-group form-checkbox">
                                <input type="checkbox" name="setting_watermark_scale" id="watermark_scale" value="1" <?= $settings->get('watermark_scale', '0') == '1' ? 'checked' : '' ?>>
                                <label for="watermark_scale">Масштабировать водяной знак на всё изображение (растянуть)</label>
                            </div>

                            <div class="form-group">
                                <label>Позиция</label>
                                <select name="setting_watermark_position" class="form-control">
                                    <option value="top_left" <?= $settings->get('watermark_position', 'bottom_right') == 'top_left' ? 'selected' : '' ?>>Левый верхний</option>
                                    <option value="top_right" <?= $settings->get('watermark_position', 'bottom_right') == 'top_right' ? 'selected' : '' ?>>Правый верхний</option>
                                    <option value="bottom_left" <?= $settings->get('watermark_position', 'bottom_right') == 'bottom_left' ? 'selected' : '' ?>>Левый нижний</option>
                                    <option value="bottom_right" <?= $settings->get('watermark_position', 'bottom_right') == 'bottom_right' ? 'selected' : '' ?>>Правый нижний</option>
                                    <option value="center" <?= $settings->get('watermark_position', 'bottom_right') == 'center' ? 'selected' : '' ?>>Центр</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Отступ от краёв (px)</label>
                                <input type="number" name="setting_watermark_padding" class="form-control" value="<?= intval($settings->get('watermark_padding', 10)) ?>">
                            </div>

                            <div class="form-group">
                                <label>Прозрачность (%)</label>
                                <input type="number" name="setting_watermark_opacity" class="form-control" min="0" max="100" value="<?= intval($settings->get('watermark_opacity', 50)) ?>">
                            </div>

                            <!-- Новые поля: цвет и текст -->
                            <div class="form-group">
                                <label>Цвет водяного знака (для текстового варианта)</label>
                                <input type="color" name="setting_watermark_color" class="form-control" value="<?= htmlspecialchars($settings->get('watermark_color', '#000000')) ?>" style="height: 40px; width: 100px;">
                                <small style="color: var(--admin-gray);">Используется, если будет настроен текстовый водяной знак.</small>
                            </div>

                            <div class="form-group">
                                <label>Текст водяного знака (если нужен текстовый вариант)</label>
                                <input type="text" name="setting_watermark_text" class="form-control" value="<?= htmlspecialchars($settings->get('watermark_text', '')) ?>" placeholder="например, © БурСервис">
                                <small style="color: var(--admin-gray);">Оставьте пустым, если используете только логотип.</small>
                            </div>
                        </div>
                        
                        <div style="margin-top: 30px; display: flex; gap: 15px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Сохранить все настройки
                            </button>
                            <a href="dashboard.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Отмена
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/assets/js/admin.js?v=<?= time() ?>"></script>
    
    <script>
        // Предпросмотр логотипа
        function previewLogo(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('logoPreview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Переключение вкладок
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.settings-tab');
            const sections = document.querySelectorAll('.settings-section');

            if (tabs.length === 0 || sections.length === 0) {
                console.warn('Вкладки или секции не найдены');
                return;
            }

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.dataset.tab;
                    
                    tabs.forEach(t => t.classList.remove('active'));
                    sections.forEach(s => s.classList.remove('active'));
                    
                    this.classList.add('active');
                    
                    const activeSection = document.getElementById(`tab-${tabId}`);
                    if (activeSection) {
                        activeSection.classList.add('active');
                    } else {
                        console.error('Секция с id "tab-' + tabId + '" не найдена');
                    }
                });
            });

            // Активируем первую вкладку по умолчанию (если ни одна не активна)
            if (!document.querySelector('.settings-tab.active')) {
                const firstTab = tabs[0];
                firstTab.classList.add('active');
                const firstSectionId = firstTab.dataset.tab;
                const firstSection = document.getElementById(`tab-${firstSectionId}`);
                if (firstSection) firstSection.classList.add('active');
            }
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>