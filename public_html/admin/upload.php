<?php
// ==============================================
// admin/upload.php - Отдельный загрузчик файлов
// ==============================================
session_start();
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/Auth.php';
require_once __DIR__ . '/../../app/models/Gallery.php';

// Разрешаем eval для CKEditor (CSP)
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline';");

$auth = Auth::getInstance();

// Проверка авторизации
if (!$auth->check()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $gallery = new Gallery();
    $result = $gallery->upload(
        $_FILES['file'],
        $_POST['alt_text'] ?? '',
        $_POST['title'] ?? ''
    );
    
    if (isset($result['error'])) {
        $error = $result['error'];
    } else {
        $success = 'Файл успешно загружен!';
        
        // Если это AJAX запрос, возвращаем JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => true, 'file' => $result]);
            exit;
        }
    }
}

// Если это AJAX запрос с ошибкой
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    echo json_encode(['error' => $error]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузка файлов | Админ-панель</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #F1F5F9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .upload-container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
        }
        
        h1 {
            color: #0B3B5C;
            margin-bottom: 30px;
            font-size: 1.8rem;
        }
        
        .upload-area {
            border: 2px dashed #E2E8F0;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        
        .upload-area:hover {
            border-color: #0B3B5C;
            background: #F8FAFC;
        }
        
        .upload-area i {
            font-size: 3rem;
            color: #64748B;
            margin-bottom: 15px;
        }
        
        .upload-area h3 {
            margin-bottom: 10px;
            color: #1E293B;
        }
        
        .upload-area p {
            color: #64748B;
        }
        
        .file-info {
            margin-top: 20px;
            padding: 15px;
            background: #F8FAFC;
            border-radius: 8px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
        }
        
        .btn {
            background: #0B3B5C;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #072A44;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="upload-container">
        <h1><i class="fas fa-cloud-upload-alt"></i> Загрузка файлов</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                <i class="fas fa-cloud-upload-alt"></i>
                <h3>Перетащите файлы сюда или нажмите для выбора</h3>
                <p>Поддерживаются форматы: JPG, PNG, GIF, WEBP. Максимальный размер: 10MB</p>
                <input type="file" name="file" id="fileInput" accept="image/*" style="display: none;" onchange="document.getElementById('uploadForm').submit()">
            </div>
            
            <div class="file-info">
                <label>Alt текст (для SEO):</label>
                <input type="text" name="alt_text" class="form-control" style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #E2E8F0; border-radius: 6px;">
                
                <label style="margin-top: 15px; display: block;">Название:</label>
                <input type="text" name="title" class="form-control" style="width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #E2E8F0; border-radius: 6px;">
            </div>
            
            <button type="submit" class="btn" style="margin-top: 20px; width: 100%;">
                <i class="fas fa-upload"></i> Загрузить файл
            </button>
        </form>
    </div>
    
    <script>
        // Drag & Drop
        const uploadArea = document.querySelector('.upload-area');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.style.borderColor = '#0B3B5C';
                uploadArea.style.background = '#F1F5F9';
            });
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.style.borderColor = '#E2E8F0';
                uploadArea.style.background = '#F8FAFC';
            });
        });
        
        uploadArea.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('fileInput').files = files;
            document.getElementById('uploadForm').submit();
        });
    </script>
</body>
</html>