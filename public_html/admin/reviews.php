<?php
// ==============================================
// admin/reviews.php - Управление отзывами
// ==============================================
session_start();
// Разрешаем eval для CKEditor (CSP)
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline';");

require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/Auth.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/models/Settings.php';
require_once __DIR__ . '/../../app/models/Review.php';
require_once __DIR__ . '/../../app/models/Project.php';
require_once __DIR__ . '/../../app/models/Gallery.php';

$auth = Auth::getInstance();
$auth->requireLogin();

$settings = new Settings();
$reviewModel = new Review();
$projectModel = new Project();
$gallery = new Gallery();

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// Получаем проекты для выбора
$projects = $projectModel->getAllPublished();

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Недействительный CSRF токен';
    } else {
        switch ($_POST['action']) {
            case 'create':
                $data = [
                    'author' => $_POST['author'],
                    'rating' => intval($_POST['rating']),
                    'text' => $_POST['text'],
                    'project_id' => !empty($_POST['project_id']) ? $_POST['project_id'] : null,
                    'project_title' => $_POST['project_title'] ?? null,
                    'is_published' => isset($_POST['is_published']) ? 1 : 0
                ];
                
                if ($reviewModel->create($data)) {
                    $message = 'Отзыв успешно добавлен!';
                    $action = 'list';
                } else {
                    $error = 'Ошибка при добавлении отзыва';
                }
                break;
                
            case 'update':
                $data = [
                    'author' => $_POST['author'],
                    'rating' => intval($_POST['rating']),
                    'text' => $_POST['text'],
                    'project_id' => !empty($_POST['project_id']) ? $_POST['project_id'] : null,
                    'project_title' => $_POST['project_title'] ?? null,
                    'is_published' => isset($_POST['is_published']) ? 1 : 0
                ];
                
                if ($reviewModel->update($id, $data)) {
                    $message = 'Отзыв успешно обновлен!';
                    $action = 'list';
                } else {
                    $error = 'Ошибка при обновлении отзыва';
                }
                break;
                
            case 'delete':
                if ($reviewModel->delete($_POST['id'])) {
                    $message = 'Отзыв успешно удален!';
                } else {
                    $error = 'Ошибка при удалении отзыва';
                }
                $action = 'list';
                break;
        }
    }
}

// Получаем список отзывов
$reviews = $reviewModel->getAllPublished();
$review = $id ? $reviewModel->getById($id) : null;

$current_user = $auth->user();
$csrf_token = $auth->generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление отзывами | Админ-панель</title>
    
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= time() ?>">
    
    <style>
        .rating-stars {
            display: flex;
            gap: 5px;
            margin: 10px 0;
        }
        
        .rating-star {
            color: #CBD5E0;
            cursor: pointer;
            font-size: 1.5rem;
            transition: color 0.3s;
        }
        
        .rating-star.active {
            color: #FAB13B;
        }
        
        .rating-star:hover {
            color: #FAB13B;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Сайдбар -->

        <!-- ИЗМЕНЕНО: Вместо жёстко прописанного сайдбара подключаем общий файл sidebar.php -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Основной контент -->
        <main class="admin-main">
            <div class="admin-header">
                <div class="admin-title">
                    <h1>
                        <?php if ($action === 'create'): ?>
                            Добавление отзыва
                        <?php elseif ($action === 'edit' && $review): ?>
                            Редактирование отзыва: <?= htmlspecialchars($review['author']) ?>
                        <?php else: ?>
                            Управление отзывами
                        <?php endif; ?>
                    </h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Дашборд</a> / 
                        <a href="reviews.php">Отзывы</a> /
                        <?php if ($action === 'create'): ?>
                            Добавление
                        <?php elseif ($action === 'edit'): ?>
                            Редактирование
                        <?php else: ?>
                            Список
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($action === 'list'): ?>
                    <div class="admin-actions">
                        <a href="?action=create" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i>
                            Добавить отзыв
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'list'): ?>
                <!-- Список отзывов -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2><i class="fas fa-star"></i> Все отзывы</h2>
                    </div>
                    <div class="admin-card-body">
                        <?php if (empty($reviews)): ?>
                            <p style="text-align: center; color: var(--admin-gray); padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                                Нет добавленных отзывов
                            </p>
                        <?php else: ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">ID</th>
                                        <th>Автор</th>
                                        <th>Рейтинг</th>
                                        <th>Отзыв</th>
                                        <th>Проект</th>
                                        <th>Дата</th>
                                        <th style="width: 100px;">Статус</th>
                                        <th style="width: 150px;">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reviews as $item): ?>
                                        <tr>
                                            <td>#<?= $item['id'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($item['author']) ?></strong>
                                            </td>
                                            <td>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star" style="color: <?= $i <= $item['rating'] ? '#FAB13B' : '#E2E8F0' ?>;"></i>
                                                <?php endfor; ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars(truncate($item['text'], 50)) ?>
                                            </td>
                                            <td><?= htmlspecialchars($item['project_title'] ?? '—') ?></td>
                                            <td><?= format_date($item['created_at'], 'd.m.Y') ?></td>
                                            <td>
                                                <?php if ($item['is_published']): ?>
                                                    <span style="color: #10B981;">Активно</span>
                                                <?php else: ?>
                                                    <span style="color: #EF4444;">Скрыто</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?action=edit&id=<?= $item['id'] ?>" class="btn btn-sm btn-outline" title="Редактировать">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline" style="color: #EF4444;" 
                                                            onclick="return confirm('Вы уверены, что хотите удалить отзыв?')" title="Удалить">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php elseif ($action === 'create' || ($action === 'edit' && $review)): ?>
                <!-- Форма добавления/редактирования отзыва -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2>
                            <i class="fas <?= $action === 'create' ? 'fa-plus-circle' : 'fa-edit' ?>"></i>
                            <?= $action === 'create' ? 'Новый отзыв' : 'Редактирование отзыва' ?>
                        </h2>
                    </div>
                    <div class="admin-card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
                            
                            <div class="form-group">
                                <label>Имя автора *</label>
                                <input type="text" name="author" class="form-control" required
                                       value="<?= htmlspecialchars($review['author'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Рейтинг</label>
                                <div class="rating-stars" id="ratingStars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star rating-star <?= isset($review['rating']) && $i <= $review['rating'] ? 'active' : '' ?>" 
                                           data-rating="<?= $i ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="rating" id="rating" value="<?= intval($review['rating'] ?? 5) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Текст отзыва *</label>
                                <textarea name="text" class="form-control" rows="5" required><?= htmlspecialchars($review['text'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Связанный проект</label>
                                    <select name="project_id" class="form-control" id="projectSelect">
                                        <option value="">Без проекта</option>
                                        <?php foreach ($projects as $project): ?>
                                            <option value="<?= $project['id'] ?>" <?= ($review['project_id'] ?? '') == $project['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($project['title']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Название проекта (если не выбран выше)</label>
                                    <input type="text" name="project_title" class="form-control" 
                                           value="<?= htmlspecialchars($review['project_title'] ?? '') ?>"
                                           placeholder="Например: Бурение на даче">
                                </div>
                            </div>
                            
                            <div class="form-checkbox" style="margin-bottom: 20px;">
                                <input type="checkbox" name="is_published" id="is_published" 
                                       <?= !isset($review['is_published']) || $review['is_published'] ? 'checked' : '' ?>>
                                <label for="is_published">Опубликовано</label>
                            </div>
                            
                            <div style="display: flex; gap: 15px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    <?= $action === 'create' ? 'Добавить отзыв' : 'Сохранить изменения' ?>
                                </button>
                                <a href="reviews.php" class="btn btn-outline">
                                    <i class="fas fa-times"></i>
                                    Отмена
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/assets/js/admin.js?v=<?= time() ?>"></script>
    
    <script>
        // Обработчик звезд рейтинга
        const stars = document.querySelectorAll('.rating-star');
        const ratingInput = document.getElementById('rating');
        
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.dataset.rating);
                ratingInput.value = rating;
                
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
            
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.dataset.rating);
                
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.style.color = '#FAB13B';
                    } else {
                        s.style.color = '#CBD5E0';
                    }
                });
            });
            
            star.addEventListener('mouseleave', function() {
                const currentRating = parseInt(ratingInput.value);
                
                stars.forEach((s, index) => {
                    if (index < currentRating) {
                        s.style.color = '#FAB13B';
                    } else {
                        s.style.color = '#CBD5E0';
                    }
                });
            });
        });
        
        // Автозаполнение названия проекта при выборе из списка
        const projectSelect = document.getElementById('projectSelect');
        const projectTitle = document.querySelector('input[name="project_title"]');
        
        if (projectSelect && projectTitle) {
            projectSelect.addEventListener('change', function() {
                if (this.value) {
                    const selectedOption = this.options[this.selectedIndex];
                    projectTitle.value = selectedOption.text;
                } else {
                    projectTitle.value = '';
                }
            });
        }
    </script>
</body>
</html>