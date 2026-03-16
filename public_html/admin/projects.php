<?php
// ==============================================
// admin/projects.php - Управление проектами
// ==============================================
session_start();
// Разрешаем eval для CKEditor (CSP)
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline';");

require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/Auth.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/models/Settings.php';
require_once __DIR__ . '/../../app/models/Project.php';
require_once __DIR__ . '/../../app/models/Gallery.php';

$auth = Auth::getInstance();
$auth->requireLogin();

$settings = new Settings();
$projectModel = new Project();
$gallery = new Gallery();

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// Получаем список изображений для выбора
$images = $gallery->getAll();

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Недействительный CSRF токен';
    } else {
        switch ($_POST['action']) {
            case 'create':
                $data = [
                    'title' => $_POST['title'],
                    'client' => $_POST['client'] ?? null,
                    'location' => $_POST['location'] ?? null,
                    'depth' => $_POST['depth'] ?? null,
                    'description' => $_POST['description'] ?? null,
                    'content' => $_POST['content'] ?? null,
                    'cover_image_id' => !empty($_POST['cover_image_id']) ? $_POST['cover_image_id'] : null,
                    'completion_date' => $_POST['completion_date'] ?? null,
                    'sort_order' => intval($_POST['sort_order']),
                    'is_published' => isset($_POST['is_published']) ? 1 : 0
                ];
                
                if ($projectModel->create($data)) {
                    $project_id = $pdo->lastInsertId();
                    
                    // Добавляем фото в галерею проекта
                    if (!empty($_POST['gallery_images'])) {
                        foreach ($_POST['gallery_images'] as $image_id) {
                            $projectModel->addPhoto($project_id, $image_id);
                        }
                    }
                    
                    $message = 'Проект успешно добавлен!';
                    $action = 'list';
                } else {
                    $error = 'Ошибка при добавлении проекта';
                }
                break;
                
            case 'update':
                $data = [
                    'title' => $_POST['title'],
                    'client' => $_POST['client'] ?? null,
                    'location' => $_POST['location'] ?? null,
                    'depth' => $_POST['depth'] ?? null,
                    'description' => $_POST['description'] ?? null,
                    'content' => $_POST['content'] ?? null,
                    'cover_image_id' => !empty($_POST['cover_image_id']) ? $_POST['cover_image_id'] : null,
                    'completion_date' => $_POST['completion_date'] ?? null,
                    'sort_order' => intval($_POST['sort_order']),
                    'is_published' => isset($_POST['is_published']) ? 1 : 0
                ];
                
                if ($projectModel->update($id, $data)) {
                    $message = 'Проект успешно обновлен!';
                    $action = 'list';
                } else {
                    $error = 'Ошибка при обновлении проекта';
                }
                break;
                
            case 'delete':
                if ($projectModel->delete($_POST['id'])) {
                    $message = 'Проект успешно удален!';
                } else {
                    $error = 'Ошибка при удалении проекта';
                }
                $action = 'list';
                break;
                
            case 'add_photo':
                if ($projectModel->addPhoto($id, $_POST['gallery_id'])) {
                    $message = 'Фото добавлено в проект';
                } else {
                    $error = 'Ошибка при добавлении фото';
                }
                break;
                
            case 'remove_photo':
                if ($projectModel->removePhoto($id, $_POST['gallery_id'])) {
                    $message = 'Фото удалено из проекта';
                } else {
                    $error = 'Ошибка при удалении фото';
                }
                break;
        }
    }
}

// Получаем список проектов
$projects = $projectModel->getAllPublished();
$project = $id ? $projectModel->getById($id) : null;
$project_gallery = $id ? $projectModel->getGallery($id) : [];

$current_user = $auth->user();
$csrf_token = $auth->generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление проектами | Админ-панель</title>
    
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= time() ?>">
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
                            Добавление проекта
                        <?php elseif ($action === 'edit' && $project): ?>
                            Редактирование: <?= htmlspecialchars($project['title']) ?>
                        <?php else: ?>
                            Управление проектами
                        <?php endif; ?>
                    </h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Дашборд</a> / 
                        <a href="projects.php">Проекты</a> /
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
                            Добавить проект
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
                <!-- Список проектов -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2><i class="fas fa-hard-hat"></i> Все проекты</h2>
                    </div>
                    <div class="admin-card-body">
                        <?php if (empty($projects)): ?>
                            <p style="text-align: center; color: var(--admin-gray); padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                                Нет добавленных проектов
                            </p>
                        <?php else: ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">ID</th>
                                        <th>Название</th>
                                        <th>Клиент</th>
                                        <th>Локация</th>
                                        <th>Глубина</th>
                                        <th>Дата</th>
                                        <th style="width: 100px;">Статус</th>
                                        <th style="width: 150px;">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $item): ?>
                                        <tr>
                                            <td>#<?= $item['id'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($item['title']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($item['client'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars($item['location'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars($item['depth'] ?? '—') ?></td>
                                            <td><?= $item['completion_date'] ? format_date($item['completion_date']) : '—' ?></td>
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
                                                            onclick="return confirm('Вы уверены, что хотите удалить проект?')" title="Удалить">
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
                
            <?php elseif ($action === 'create' || ($action === 'edit' && $project)): ?>
                <!-- Форма добавления/редактирования проекта -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2>
                            <i class="fas <?= $action === 'create' ? 'fa-plus-circle' : 'fa-edit' ?>"></i>
                            <?= $action === 'create' ? 'Новый проект' : 'Редактирование проекта' ?>
                        </h2>
                    </div>
                    <div class="admin-card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
                            
                            <div class="form-group">
                                <label>Название проекта *</label>
                                <input type="text" name="title" class="form-control" required
                                       value="<?= htmlspecialchars($project['title'] ?? '') ?>">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Клиент</label>
                                    <input type="text" name="client" class="form-control" 
                                           value="<?= htmlspecialchars($project['client'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Локация</label>
                                    <input type="text" name="location" class="form-control" 
                                           value="<?= htmlspecialchars($project['location'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Глубина скважины</label>
                                    <input type="text" name="depth" class="form-control" 
                                           placeholder="например: 45 м"
                                           value="<?= htmlspecialchars($project['depth'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Дата завершения</label>
                                    <input type="date" name="completion_date" class="form-control" 
                                           value="<?= htmlspecialchars($project['completion_date'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Краткое описание</label>
                                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Полное описание</label>
                                <textarea name="content" class="form-control editor" rows="6"><?= htmlspecialchars($project['content'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Обложка проекта</label>
                                    <select name="cover_image_id" class="form-control">
                                        <option value="">Выберите изображение</option>
                                        <?php foreach ($images as $image): ?>
                                            <option value="<?= $image['id'] ?>" <?= ($project['cover_image_id'] ?? '') == $image['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($image['title'] ?: $image['original_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Порядок сортировки</label>
                                    <input type="number" name="sort_order" class="form-control" 
                                           value="<?= intval($project['sort_order'] ?? 0) ?>">
                                </div>
                            </div>
                            
                            <div class="form-checkbox" style="margin-bottom: 20px;">
                                <input type="checkbox" name="is_published" id="is_published" 
                                       <?= !isset($project['is_published']) || $project['is_published'] ? 'checked' : '' ?>>
                                <label for="is_published">Опубликовано</label>
                            </div>
                            
                            <?php if ($action === 'edit'): ?>
                                <!-- Галерея проекта -->
                                <h3 style="margin: 30px 0 20px;">Галерея проекта</h3>
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                                    <?php foreach ($project_gallery as $photo): ?>
                                        <div style="position: relative;">
                                            <img src="<?= htmlspecialchars($photo['thumbnail'] ?? $photo['filepath']) ?>" 
                                                 style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px;">
                                            <form method="POST" style="position: absolute; top: 5px; right: 5px;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="action" value="remove_photo">
                                                <input type="hidden" name="gallery_id" value="<?= $photo['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline" style="background: white; color: #EF4444;"
                                                        onclick="return confirm('Удалить фото из проекта?')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                            <p style="font-size: 0.75rem; margin-top: 5px;"><?= htmlspecialchars($photo['original_name']) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div style="margin-bottom: 20px;">
                                    <h4 style="margin-bottom: 10px;">Добавить фото</h4>
                                    <div style="display: flex; gap: 10px;">
                                        <select name="gallery_id" class="form-control" style="width: 300px;">
                                            <option value="">Выберите изображение</option>
                                            <?php foreach ($images as $image): ?>
                                                <?php if (!in_array($image['id'], array_column($project_gallery, 'id'))): ?>
                                                    <option value="<?= $image['id'] ?>">
                                                        <?= htmlspecialchars($image['title'] ?: $image['original_name']) ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="action" value="add_photo" class="btn btn-outline">
                                            <i class="fas fa-plus"></i> Добавить
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 15px; margin-top: 30px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    <?= $action === 'create' ? 'Создать проект' : 'Сохранить изменения' ?>
                                </button>
                                <a href="projects.php" class="btn btn-outline">
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
    <script src="https://cdn.ckeditor.com/4.16.2/standard/ckeditor.js"></script>
    <script>
        CKEDITOR.replace('content', {
            language: 'ru',
            height: 400
        });
    </script>
</body>
</html>