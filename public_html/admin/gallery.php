<?php
// ==============================================
// admin/gallery.php - Медиабиблиотека (финальная версия)
// ==============================================
session_start();
ob_start(); // Включаем буферизацию вывода

// Разрешаем eval для CKEditor (CSP)
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
$auth->requireLogin();

$settings = new Settings();
$gallery = new Gallery();

// Обработка POST-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('Недействительный CSRF токен', 'error');
        ob_clean();
        header('Location: gallery.php' . (isset($_GET['view']) ? '?view=' . $_GET['view'] : ''));
        exit;
    }

    // Загрузка файла
    if (isset($_FILES['file'])) {
        $result = $gallery->upload(
            $_FILES['file'],
            $_POST['alt_text'] ?? '',
            $_POST['title'] ?? ''
        );
        if (isset($result['error'])) {
            setFlash($result['error'], 'error');
        } else {
            setFlash('Файл успешно загружен!', 'success');
        }
        ob_clean();
        header('Location: gallery.php' . (isset($_GET['view']) ? '?view=' . $_GET['view'] : ''));
        exit;
    }

    // Удаление одного файла
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
        if ($gallery->delete($_POST['id'])) {
            setFlash('Файл успешно удален!', 'success');
        } else {
            setFlash('Ошибка при удалении файла', 'error');
        }
        ob_clean();
        header('Location: gallery.php' . (isset($_GET['view']) ? '?view=' . $_GET['view'] : ''));
        exit;
    }

    // Обновление информации об изображении
    if (isset($_POST['action']) && $_POST['action'] === 'update' && isset($_POST['id'])) {
        // Дополнительная проверка, что это действительно запрос на обновление
        if (!isset($_POST['title'])) {
            setFlash('Некорректный запрос на обновление', 'error');
            ob_clean();
            header('Location: gallery.php?id=' . $_POST['id'] . (isset($_GET['view']) ? '&view=' . $_GET['view'] : ''));
            exit;
        }

        $data = [
            'alt_text' => $_POST['alt_text'],
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'sort_order' => intval($_POST['sort_order']),
            'is_published' => isset($_POST['is_published']) ? 1 : 0
        ];

        if ($gallery->update($_POST['id'], $data)) {
            setFlash('Информация об изображении обновлена!', 'success');
        } else {
            setFlash('Ошибка при обновлении информации', 'error');
        }
        ob_clean();
        header('Location: gallery.php?id=' . $_POST['id'] . (isset($_GET['view']) ? '&view=' . $_GET['view'] : ''));
        exit;
    }

    // Индивидуальное наложение водяного знака
    if (isset($_POST['action']) && $_POST['action'] === 'apply_watermark_single' && isset($_POST['id'])) {
        $result = $gallery->applyWatermark($_POST['id']);
        if ($result !== false) {
            $msg = 'Водяной знак успешно наложен. ' . (is_numeric($result) ? 'Создана новая запись с ID ' . $result : '');
            setFlash($msg, 'success');
        } else {
            setFlash('Не удалось наложить водяной знак. Возможно, изображение уже содержит водяной знак или логотип не найден.', 'error');
        }
        ob_clean();
        header('Location: gallery.php?id=' . $_POST['id'] . (isset($_GET['view']) ? '&view=' . $_GET['view'] : ''));
        exit;
    }
}

// Получаем список изображений
$images = $gallery->getAll();
$csrf_token = $auth->generateCsrfToken();
$current_user = $auth->user();

// Режим просмотра
$view_mode = $_GET['view'] ?? 'grid';
$image_id = $_GET['id'] ?? 0;
$current_image = $image_id ? $gallery->getById($image_id) : null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Медиабиблиотека | Админ-панель</title>
    
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= time() ?>">
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header">
                <div class="admin-title">
                    <h1>Медиабиблиотека</h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Дашборд</a> / Медиабиблиотека
                    </div>
                </div>
            </div>
            
            <?php $flash = getFlash(); ?>
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($current_image): ?>
                <!-- Редактирование изображения -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2><i class="fas fa-edit"></i> Редактирование изображения</h2>
                        <a href="gallery.php" class="btn btn-sm btn-outline"><i class="fas fa-arrow-left"></i> Вернуться</a>
                    </div>
                    <div class="admin-card-body">
                        <div style="display: grid; grid-template-columns: 300px 1fr; gap: 30px;">
                            <div>
                                <img src="<?= htmlspecialchars($current_image['filepath']) ?>" alt="" style="width:100%; border-radius:8px;">
                                <div style="margin-top: 20px;">
                                    <button class="btn btn-outline btn-block" onclick="previewImage('<?= $current_image['filepath'] ?>')"><i class="fas fa-search-plus"></i> Просмотр</button>
                                    <!-- Кнопка наложения водяного знака (индивидуально) -->
                                    <form method="POST" style="margin-top:10px;">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="action" value="apply_watermark_single">
                                        <input type="hidden" name="id" value="<?= $current_image['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-block" onclick="return confirm('Наложить водяной знак на это изображение?')">
                                            <i class="fas fa-water"></i> Наложить водяной знак
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div>
                                <!-- Форма обновления (отдельно, не вложена в другие формы) -->
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= $current_image['id'] ?>">
                                    
                                    <div class="form-group">
                                        <label>Название</label>
                                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($current_image['title'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Alt текст</label>
                                        <input type="text" name="alt_text" class="form-control" value="<?= htmlspecialchars($current_image['alt_text'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Описание</label>
                                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($current_image['description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Порядок сортировки</label>
                                            <input type="number" name="sort_order" class="form-control" value="<?= intval($current_image['sort_order'] ?? 0) ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Статус</label>
                                            <div class="form-checkbox" style="margin-top:10px;">
                                                <input type="checkbox" name="is_published" id="is_published" <?= $current_image['is_published'] ? 'checked' : '' ?>>
                                                <label for="is_published">Опубликовано</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Информация о файле</label>
                                        <div style="background:#F8FAFC; padding:15px; border-radius:8px;">
                                            <p><strong>Оригинальное имя:</strong> <?= htmlspecialchars($current_image['original_name']) ?></p>
                                            <p><strong>Тип:</strong> <?= $current_image['mime_type'] ?></p>
                                            <p><strong>Размер:</strong> <?= get_file_size($current_image['filesize']) ?></p>
                                            <p><strong>Размеры:</strong> <?= $current_image['width'] ?>x<?= $current_image['height'] ?> px</p>
                                            <p><strong>Загружено:</strong> <?= format_date($current_image['created_at'], 'd.m.Y H:i') ?></p>
                                            <p><strong>Водяной знак:</strong> <?= isset($current_image['watermarked']) && $current_image['watermarked'] ? '✓ наложен' : '—' ?></p>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; gap: 15px;">
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить изменения</button>
                                    </div>
                                </form>
                                
                                <!-- Форма удаления (отдельно) -->
                                <form method="POST" style="margin-top: 15px;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $current_image['id'] ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить это изображение?')"><i class="fas fa-trash"></i> Удалить</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Область загрузки -->
                <div class="upload-area" onclick="document.getElementById('file-input').click()">
                    <form method="POST" enctype="multipart/form-data" id="upload-form">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="file" name="file" id="file-input" accept="image/*" style="display:none;" onchange="document.getElementById('upload-form').submit()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h3>Перетащите файлы сюда или нажмите для выбора</h3>
                        <p>Поддерживаются форматы: JPG, PNG, GIF, WEBP. Максимальный размер: 10MB</p>
                    </form>
                </div>
                
                <!-- Панель инструментов -->
                <div class="gallery-toolbar">
                    <div><span style="color:var(--admin-gray);">Всего файлов: <?= count($images) ?></span></div>
                    <div class="view-toggle">
                        <a href="?view=grid" class="<?= $view_mode === 'grid' ? 'active' : '' ?>"><i class="fas fa-th"></i> Сетка</a>
                        <a href="?view=list" class="<?= $view_mode === 'list' ? 'active' : '' ?>"><i class="fas fa-list"></i> Список</a>
                    </div>
                </div>

                <!-- Галерея (без чекбоксов) -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2><i class="fas fa-images"></i> Все изображения</h2>
                    </div>
                    <div class="admin-card-body">
                        <?php if (empty($images)): ?>
                            <p style="text-align:center; color:var(--admin-gray); padding:40px;">
                                <i class="fas fa-images" style="font-size:3rem; margin-bottom:15px; display:block;"></i>
                                В медиабиблиотеке пока нет изображений
                            </p>
                        <?php else: ?>
                            <?php if ($view_mode === 'grid'): ?>
                                <!-- Режим сетки -->
                                <div class="gallery-grid">
                                    <?php foreach ($images as $image): ?>
                                        <div class="gallery-item">
                                            <img src="<?= htmlspecialchars($image['thumbnail'] ?? $image['filepath']) ?>" 
                                                 alt="<?= htmlspecialchars($image['alt_text']) ?>" class="gallery-image"
                                                 onclick="previewImage('<?= $image['filepath'] ?>')">
                                            <div class="gallery-info">
                                                <p style="font-weight:500;"><?= htmlspecialchars($image['title'] ?: $image['original_name']) ?></p>
                                                <p><?= get_file_size($image['filesize']) ?></p>
                                                <?php if (isset($image['watermarked']) && $image['watermarked']): ?>
                                                    <p><span style="color:#10B981;"><i class="fas fa-check-circle"></i> водяной знак</span></p>
                                                <?php endif; ?>
                                                <div class="gallery-actions">
                                                    <a href="?id=<?= $image['id'] ?>" class="btn btn-sm btn-outline" title="Редактировать"><i class="fas fa-edit"></i></a>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= $image['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline" style="color:#EF4444;" onclick="return confirm('Вы уверены, что хотите удалить это изображение?')" title="Удалить"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                    <button class="btn btn-sm btn-outline" onclick="copyToClipboard('<?= $image['filepath'] ?>')" title="Копировать URL"><i class="fas fa-link"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <!-- Режим списка -->
                                <div class="gallery-list">
                                    <?php foreach ($images as $image): ?>
                                        <div class="gallery-list-item">
                                            <img src="<?= htmlspecialchars($image['thumbnail'] ?? $image['filepath']) ?>" 
                                                 alt="<?= htmlspecialchars($image['alt_text']) ?>" class="list-thumb"
                                                 onclick="previewImage('<?= $image['filepath'] ?>')">
                                            <div class="list-info">
                                                <div class="list-title"><?= htmlspecialchars($image['title'] ?: $image['original_name']) ?></div>
                                                <div class="list-meta">
                                                    <?= get_file_size($image['filesize']) ?> • 
                                                    <?= $image['width'] ?>x<?= $image['height'] ?> • 
                                                    <?= format_date($image['created_at'], 'd.m.Y') ?>
                                                    <?php if (isset($image['watermarked']) && $image['watermarked']): ?>
                                                        • <span style="color:#10B981;">водяной знак</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div style="display:flex; gap:5px;">
                                                <a href="?id=<?= $image['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i></a>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $image['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline" style="color:#EF4444;" onclick="return confirm('Вы уверены?')"><i class="fas fa-trash"></i></button>
                                                </form>
                                                <button class="btn btn-sm btn-outline" onclick="copyToClipboard('<?= $image['filepath'] ?>')"><i class="fas fa-link"></i></button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Модальное окно предпросмотра -->
    <div class="preview-modal" id="previewModal" onclick="this.classList.remove('active')">
        <span class="preview-close">&times;</span>
        <img src="" alt="Preview" class="preview-content" id="previewImage">
        <div class="preview-info" id="previewInfo"></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/assets/js/admin.js?v=<?= time() ?>"></script>
    <script>
        function previewImage(src) {
            const modal = document.getElementById('previewModal');
            const img = document.getElementById('previewImage');
            const info = document.getElementById('previewInfo');
            img.src = src;
            info.innerHTML = src;
            modal.classList.add('active');
        }
        function copyToClipboard(text) {
            navigator.clipboard.writeText(window.location.origin + text).then(() => {
                alert('URL скопирован в буфер обмена');
            }).catch(() => {
                prompt('Скопируйте URL:', window.location.origin + text);
            });
        }
        // Drag & Drop
        const uploadArea = document.querySelector('.upload-area');
        ['dragenter','dragover','dragleave','drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        ['dragenter','dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.style.borderColor = '#0B3B5C';
                uploadArea.style.background = '#F1F5F9';
            });
        });
        ['dragleave','drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.style.borderColor = 'var(--admin-border)';
                uploadArea.style.background = '#F8FAFC';
            });
        });
        uploadArea.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                const input = document.getElementById('file-input');
                input.files = files;
                document.getElementById('upload-form').submit();
            }
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>