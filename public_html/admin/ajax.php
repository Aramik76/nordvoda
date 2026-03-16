<?php
// ==============================================
// admin/ajax.php - AJAX обработчик для админ-панели
// ==============================================
session_start();
// Разрешаем eval для CKEditor (CSP)
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline';");

require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/Auth.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/models/Gallery.php';
require_once __DIR__ . '/../../app/models/Service.php';
require_once __DIR__ . '/../../app/models/Project.php';

$auth = Auth::getInstance();

// Проверка авторизации
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Проверка CSRF токена
if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'upload_image':
        // Загрузка изображения через AJAX
        $gallery = new Gallery();
        
        if (isset($_FILES['file'])) {
            $result = $gallery->upload($_FILES['file'], $_POST['alt_text'] ?? '', $_POST['title'] ?? '');
            
            if (isset($result['error'])) {
                echo json_encode(['error' => $result['error']]);
            } else {
                echo json_encode([
                    'success' => true,
                    'id' => $result['id'],
                    'url' => $result['filepath'],
                    'thumbnail' => $result['thumbnail']
                ]);
            }
        }
        break;
        
    case 'get_services':
        // Получение списка услуг
        $serviceModel = new Service();
        $services = $serviceModel->getAllPublished();
        echo json_encode($services);
        break;
        
    case 'get_projects':
        // Получение списка проектов
        $projectModel = new Project();
        $projects = $projectModel->getAllPublished();
        echo json_encode($projects);
        break;
        
    case 'get_gallery':
        // Получение списка изображений
        $gallery = new Gallery();
        $images = $gallery->getAll();
        echo json_encode($images);
        break;
        
    case 'delete_image':
        // Удаление изображения
        if (isset($_POST['id'])) {
            $gallery = new Gallery();
            if ($gallery->delete($_POST['id'])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Failed to delete image']);
            }
        }
        break;
        
    case 'update_sort_order':
        // Обновление порядка сортировки
        if (isset($_POST['items']) && isset($_POST['type'])) {
            $table = '';
            switch ($_POST['type']) {
                case 'services':
                    $table = 'services';
                    break;
                case 'projects':
                    $table = 'projects';
                    break;
                case 'gallery':
                    $table = 'gallery';
                    break;
            }
            
            if ($table) {
                $db = new Database();
                $conn = $db->getConnection();
                
                foreach ($_POST['items'] as $index => $id) {
                    $query = "UPDATE {$table} SET sort_order = :sort_order WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([':sort_order' => $index, ':id' => $id]);
                }
                
                echo json_encode(['success' => true]);
            }
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}