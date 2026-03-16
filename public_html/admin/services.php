<?php
// ==============================================
// admin/services.php - Управление услугами
// ==============================================
session_start();
// Разрешаем eval для CKEditor (CSP)
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline';");

require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/Auth.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/models/Settings.php';
require_once __DIR__ . '/../../app/models/Service.php';
require_once __DIR__ . '/../../app/models/Gallery.php';

$auth = Auth::getInstance();
$auth->requireLogin();

$settings = new Settings();
$serviceModel = new Service();
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
                    'description' => $_POST['description'],
                    'full_description' => $_POST['full_description'] ?? null,
                    'icon' => $_POST['icon'],
                    'image_id' => !empty($_POST['image_id']) ? $_POST['image_id'] : null,
                    'price' => $_POST['price'],
                    'features' => json_encode(explode("\n", trim($_POST['features']))),
                    'sort_order' => intval($_POST['sort_order']),
                    'is_published' => isset($_POST['is_published']) ? 1 : 0
                ];
                
                if ($serviceModel->create($data)) {
                    $message = 'Услуга успешно добавлена!';
                    $action = 'list';
                } else {
                    $error = 'Ошибка при добавлении услуги';
                }
                break;
                
            case 'update':
                $data = [
                    'title' => $_POST['title'],
                    'description' => $_POST['description'],
                    'full_description' => $_POST['full_description'] ?? null,
                    'icon' => $_POST['icon'],
                    'image_id' => !empty($_POST['image_id']) ? $_POST['image_id'] : null,
                    'price' => $_POST['price'],
                    'features' => json_encode(explode("\n", trim($_POST['features']))),
                    'sort_order' => intval($_POST['sort_order']),
                    'is_published' => isset($_POST['is_published']) ? 1 : 0
                ];
                
                if ($serviceModel->update($id, $data)) {
                    $message = 'Услуга успешно обновлена!';
                    $action = 'list';
                } else {
                    $error = 'Ошибка при обновлении услуги';
                }
                break;
                
            case 'delete':
                if ($serviceModel->delete($_POST['id'])) {
                    $message = 'Услуга успешно удалена!';
                } else {
                    $error = 'Ошибка при удалении услуги';
                }
                $action = 'list';
                break;
        }
    }
}

// Получаем список услуг
$services = $serviceModel->getAllPublished();
$service = $id ? $serviceModel->getById($id) : null;

$csrf_token = $auth->generateCsrfToken();
$current_user = $auth->user();

// Доступные иконки
$icons = [
    'fas fa-water' => 'Вода',
    'fas fa-tools' => 'Инструменты',
    'fas fa-wrench' => 'Гаечный ключ',
    'fas fa-hard-hat' => 'Каска',
    'fas fa-truck' => 'Грузовик',
    'fas fa-flask' => 'Колба',
    'fas fa-ruler' => 'Линейка',
    'fas fa-cog' => 'Шестеренка',
    'fas fa-bolt' => 'Молния',
    'fas fa-leaf' => 'Лист',
    'fas fa-shield-alt' => 'Щит',
    'fas fa-clock' => 'Часы',
    'fas fa-chart-line' => 'График',
    'fas fa-crown' => 'Корона',
    'fas fa-gem' => 'Алмаз'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление услугами | Админ-панель</title>
    
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= time() ?>">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Сайдбар -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Основной контент -->
        <main class="admin-main">
            <div class="admin-header">
                <div class="admin-title">
                    <h1>
                        <?php if ($action === 'create'): ?>
                            Добавление услуги
                        <?php elseif ($action === 'edit' && $service): ?>
                            Редактирование услуги: <?= htmlspecialchars($service['title']) ?>
                        <?php else: ?>
                            Управление услугами
                        <?php endif; ?>
                    </h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Дашборд</a> / 
                        <a href="services.php">Услуги</a> /
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
                            Добавить услугу
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
                <!-- Список услуг -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2><i class="fas fa-list"></i> Все услуги</h2>
                    </div>
                    <div class="admin-card-body">
                        <?php if (empty($services)): ?>
                            <p style="text-align: center; color: var(--admin-gray); padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                                Нет добавленных услуг
                                <br>
                                <a href="?action=create" style="color: var(--admin-primary); margin-top: 15px; display: inline-block;">
                                    Добавить первую услугу
                                </a>
                            </p>
                        <?php else: ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">ID</th>
                                        <th style="width: 50px;">Изобр.</th>
                                        <th style="width: 50px;">Иконка</th>
                                        <th>Название</th>
                                        <th>Описание</th>
                                        <th>Цена</th>
                                        <th style="width: 100px;">Сортировка</th>
                                        <th style="width: 100px;">Статус</th>
                                        <th style="width: 150px;">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($services as $item): ?>
                                        <tr>
                                            <td>#<?= $item['id'] ?></td>
                                            <td>
                                                <?php if ($item['image_id']): 
                                                    $img = $gallery->getById($item['image_id']);
                                                ?>
                                                    <img src="<?= htmlspecialchars($img['thumbnail'] ?? $img['filepath']) ?>" 
                                                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                                <?php else: ?>
                                                    <span style="color: #ccc;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size: 1.2rem; color: var(--admin-primary);">
                                                <i class="<?= htmlspecialchars($item['icon'] ?? 'fas fa-water') ?>"></i>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($item['title']) ?></strong>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars(truncate($item['description'], 50)) ?>
                                            </td>
                                            <td><?= htmlspecialchars($item['price']) ?></td>
                                            <td><?= $item['sort_order'] ?></td>
                                            <td>
                                                <?php if ($item['is_published']): ?>
                                                    <span style="color: #10B981;">
                                                        <i class="fas fa-circle" style="font-size: 0.5rem; vertical-align: middle;"></i>
                                                        Активно
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #EF4444;">
                                                        <i class="fas fa-circle" style="font-size: 0.5rem; vertical-align: middle;"></i>
                                                        Скрыто
                                                    </span>
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
                                                            onclick="return confirm('Вы уверены, что хотите удалить услугу?')" title="Удалить">
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
                
            <?php elseif ($action === 'create' || ($action === 'edit' && $service)): ?>
                <!-- Форма добавления/редактирования услуги -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2>
                            <i class="fas <?= $action === 'create' ? 'fa-plus-circle' : 'fa-edit' ?>"></i>
                            <?= $action === 'create' ? 'Добавление услуги' : 'Редактирование услуги' ?>
                        </h2>
                    </div>
                    <div class="admin-card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Название услуги *</label>
                                    <input type="text" name="title" class="form-control" required
                                           value="<?= htmlspecialchars($service['title'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Цена</label>
                                    <input type="text" name="price" class="form-control" 
                                           placeholder="от 1800 руб/м"
                                           value="<?= htmlspecialchars($service['price'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Иконка</label>
                                    <select name="icon" class="form-control">
                                        <option value="">Выберите иконку</option>
                                        <?php foreach ($icons as $icon => $name): ?>
                                            <option value="<?= $icon ?>" <?= ($service['icon'] ?? '') === $icon ? 'selected' : '' ?>>
                                                <?= $name ?> (<?= $icon ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Изображение услуги</label>
                                    <select name="image_id" class="form-control">
                                        <option value="">Нет изображения</option>
                                        <?php foreach ($images as $img): ?>
                                            <option value="<?= $img['id'] ?>" <?= ($service['image_id'] ?? '') == $img['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($img['title'] ?: $img['original_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small style="color: var(--admin-gray);">
                                        <a href="gallery.php" target="_blank">Загрузить новое изображение</a> (откроется в новой вкладке)
                                    </small>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Порядок сортировки</label>
                                    <input type="number" name="sort_order" class="form-control" 
                                           value="<?= intval($service['sort_order'] ?? 0) ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Краткое описание</label>
                                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($service['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Полное описание</label>
                                <textarea name="full_description" class="form-control editor" rows="6"><?= htmlspecialchars($service['full_description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Особенности (каждая с новой строки)</label>
                                <textarea name="features" class="form-control" rows="5"><?php 
                                    if (isset($service['features'])) {
                                        $features = json_decode($service['features'], true);
                                        echo htmlspecialchars(is_array($features) ? implode("\n", $features) : '');
                                    }
                                ?></textarea>
                            </div>
                            
                            <div class="form-checkbox" style="margin-bottom: 20px;">
                                <input type="checkbox" name="is_published" id="is_published" 
                                       <?= !isset($service['is_published']) || $service['is_published'] ? 'checked' : '' ?>>
                                <label for="is_published">Опубликовано (видно на сайте)</label>
                            </div>
                            
                            <div style="display: flex; gap: 15px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    <?= $action === 'create' ? 'Добавить услугу' : 'Сохранить изменения' ?>
                                </button>
                                <a href="services.php" class="btn btn-outline">
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
    
    <!-- Подключаем CKEditor для полного описания -->
    <script src="https://cdn.ckeditor.com/4.16.2/standard/ckeditor.js"></script>
    <script>
        CKEDITOR.replace('full_description', {
            language: 'ru',
            height: 300
        });
    </script>
</body>
</html>