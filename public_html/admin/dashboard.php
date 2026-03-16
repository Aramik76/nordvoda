<?php
// ==============================================
// admin/dashboard.php - Главная страница админ-панели
// ==============================================
session_start();

// Разрешаем eval для CKEditor (CSP)
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline';");

require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/Auth.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/models/Settings.php';
require_once __DIR__ . '/../../app/models/Service.php';
require_once __DIR__ . '/../../app/models/Project.php';
require_once __DIR__ . '/../../app/models/Review.php';
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/models/Gallery.php';
// ИЗМЕНЕНО: Подключаем модель Application для работы с заявками
require_once __DIR__ . '/../../app/models/Application.php';

$auth = Auth::getInstance();
$auth->requireLogin();

$settings = new Settings();
$serviceModel = new Service();
$projectModel = new Project();
$reviewModel = new Review();
$userModel = new User();
$galleryModel = new Gallery();
// ИЗМЕНЕНО: Создаём объект модели заявок
$appModel = new Application();

// Получаем статистику
$services_count = count($serviceModel->getAllPublished());
$projects_count = count($projectModel->getAllPublished());
$reviews_count = count($reviewModel->getAllPublished());
$users_count = count($userModel->getAll());
$images_count = count($galleryModel->getAll());
// ИЗМЕНЕНО: Получаем данные по заявкам
$total_apps = $appModel->countAll();
$new_apps = $appModel->countNew();

// Последние отзывы
$recent_reviews = $reviewModel->getAllPublished();
$recent_reviews = array_slice($recent_reviews, 0, 5);

// Последние проекты
$recent_projects = $projectModel->getLatest(5);

// Активность пользователей
$recent_users = $userModel->getAll();
usort($recent_users, function($a, $b) {
    return strtotime($b['last_login'] ?? $b['created_at']) - strtotime($a['last_login'] ?? $a['created_at']);
});
$recent_users = array_slice($recent_users, 0, 5);

// Получаем версию PHP и MySQL
$php_version = phpversion();
$db = new Database();
$pdo = $db->getConnection();
$mysql_version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

// Текущий пользователь
$current_user = $auth->user();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дашборд | Админ-панель</title>
    
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= time() ?>">
    
    <style>
        .welcome-banner {
            background: linear-gradient(135deg, #0B3B5C 0%, #1A5F7A 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .welcome-banner h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .welcome-banner p {
            opacity: 0.9;
        }
        
        .quick-actions {
            display: flex;
            gap: 15px;
        }
        
        .quick-action-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .quick-action-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--admin-border);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #F1F5F9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--admin-primary);
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .activity-time {
            font-size: 0.85rem;
            color: var(--admin-gray);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            padding: 15px;
            background: #F8FAFC;
            border-radius: 10px;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: var(--admin-gray);
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--admin-dark);
        }
        
        @media (max-width: 768px) {
            .welcome-banner {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .quick-actions {
                flex-wrap: wrap;
                justify-content: center;
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
                    <h1>Дашборд</h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Главная</a> / Дашборд
                    </div>
                </div>
                <div class="admin-actions">
                    <span class="btn btn-outline">
                        <i class="fas fa-calendar"></i>
                        <?= date('d.m.Y') ?>
                    </span>
                </div>
            </div>
            
            <!-- Приветственный баннер -->
            <div class="welcome-banner">
                <div>
                    <h2>👋 Добро пожаловать, <?= htmlspecialchars($current_user['full_name'] ?? $current_user['username']) ?>!</h2>
                    <p>Сегодня <?= date('d.m.Y') ?>. Вот что происходит на вашем сайте.</p>
                </div>
                <div class="quick-actions">
                    <a href="services.php?action=create" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i>
                        Добавить услугу
                    </a>
                    <a href="projects.php?action=create" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i>
                        Добавить проект
                    </a>
                </div>
            </div>
            
            <!-- Статистика -->
            <div class="stats-grid">

                <!-- ИЗМЕНЕНО: Карточка "Всего заявок" -->
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(239,68,68,0.1); color: #EF4444;">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Всего заявок</h3>
                        <span class="stat-number"><?= $total_apps ?></span>
                    </div>
                </div>

                <!-- ИЗМЕНЕНО: Карточка "Новые заявки" -->
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(250,177,59,0.1); color: #FAB13B;">
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Новые заявки</h3>
                        <span class="stat-number"><?= $new_apps ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(11,59,92,0.1); color: #0B3B5C;">
                        <i class="fas fa-water"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Услуги</h3>
                        <span class="stat-number"><?= $services_count ?></span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: #10B981;">
                        <i class="fas fa-hard-hat"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Проекты</h3>
                        <span class="stat-number"><?= $projects_count ?></span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(250,177,59,0.1); color: #FAB13B;">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Отзывы</h3>
                        <span class="stat-number"><?= $reviews_count ?></span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(59,130,246,0.1); color: #3B82F6;">
                        <i class="fas fa-images"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Изображения</h3>
                        <span class="stat-number"><?= $images_count ?></span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(239,68,68,0.1); color: #EF4444;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Пользователи</h3>
                        <span class="stat-number"><?= $users_count ?></span>
                    </div>
                </div>


            </div>
            
            <!-- Две колонки -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- Последние отзывы -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2><i class="fas fa-star" style="color: #FAB13B;"></i> Последние отзывы</h2>
                        <a href="reviews.php" class="btn btn-sm btn-outline">Все отзывы</a>
                    </div>
                    <div class="admin-card-body">
                        <?php if (empty($recent_reviews)): ?>
                            <p style="text-align: center; color: var(--admin-gray); padding: 30px;">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                Нет отзывов
                            </p>
                        <?php else: ?>
                            <?php foreach ($recent_reviews as $review): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <?= htmlspecialchars($review['author']) ?>
                                            <span style="margin-left: 10px; color: #FAB13B;">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= $review['rating'] ? '' : 'far' ?>"></i>
                                                <?php endfor; ?>
                                            </span>
                                        </div>
                                        <p style="color: var(--admin-gray); margin-bottom: 5px;">
                                            <?= htmlspecialchars(truncate($review['text'], 100)) ?>
                                        </p>
                                        <div class="activity-time">
                                            <i class="far fa-clock"></i> <?= time_ago($review['created_at']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Последние проекты -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2><i class="fas fa-hard-hat" style="color: #10B981;"></i> Последние проекты</h2>
                        <a href="projects.php" class="btn btn-sm btn-outline">Все проекты</a>
                    </div>
                    <div class="admin-card-body">
                        <?php if (empty($recent_projects)): ?>
                            <p style="text-align: center; color: var(--admin-gray); padding: 30px;">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                Нет проектов
                            </p>
                        <?php else: ?>
                            <?php foreach ($recent_projects as $project): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-hard-hat"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <?= htmlspecialchars($project['title']) ?>
                                        </div>
                                        <p style="color: var(--admin-gray); margin-bottom: 5px;">
                                            <?= htmlspecialchars($project['location']) ?> | Глубина: <?= htmlspecialchars($project['depth']) ?>
                                        </p>
                                        <div class="activity-time">
                                            <i class="far fa-calendar"></i> <?= format_date($project['completion_date']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Системная информация -->
            <div class="admin-card" style="margin-top: 30px;">
                <div class="admin-card-header">
                    <h2><i class="fas fa-info-circle"></i> Системная информация</h2>
                    <span class="btn btn-sm btn-outline" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Обновить
                    </span>
                </div>
                <div class="admin-card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                        <div>
                            <h3 style="margin-bottom: 15px; font-size: 1.1rem;">Окружение</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">PHP версия</div>
                                    <div class="info-value"><?= $php_version ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">MySQL версия</div>
                                    <div class="info-value"><?= $mysql_version ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Сервер</div>
                                    <div class="info-value"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Время сервера</div>
                                    <div class="info-value"><?= date('H:i:s') ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h3 style="margin-bottom: 15px; font-size: 1.1rem;">Сайт</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Название сайта</div>
                                    <div class="info-value"><?= htmlspecialchars($settings->get('site_name', 'БурСервис')) ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Версия CMS</div>
                                    <div class="info-value">1.0.0</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Последний вход</div>
                                    <div class="info-value"><?= $current_user['last_login'] ? date('d.m.Y H:i', strtotime($current_user['last_login'])) : 'Первый вход' ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Ваша роль</div>
                                    <div class="info-value">
                                        <span style="color: <?= $current_user['role'] === 'admin' ? '#0B3B5C' : '#64748B' ?>;">
                                            <?= $current_user['role'] === 'admin' ? 'Администратор' : 'Редактор' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Последние действия -->
            <div class="admin-card" style="margin-top: 30px;">
                <div class="admin-card-header">
                    <h2><i class="fas fa-history"></i> Последние действия</h2>
                </div>
                <div class="admin-card-body">
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Вы вошли в систему</div>
                                <div class="activity-time">
                                    <i class="far fa-clock"></i> <?= date('d.m.Y H:i', $_SESSION['login_time'] ?? time()) ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($services_count > 0): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-water"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Доступно услуг: <?= $services_count ?></div>
                                <div class="activity-time">
                                    <i class="far fa-calendar"></i> Актуально на сегодня
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/assets/js/admin.js?v=<?= time() ?>"></script>
    
    <script>
        // Подтверждение выхода
        document.querySelector('a[href="logout.php"]').addEventListener('click', function(e) {
            if (!confirm('Вы уверены, что хотите выйти?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>