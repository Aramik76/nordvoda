<?php
session_start();
// Разрешаем eval для CKEditor (CSP)
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline';");

require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/Auth.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/models/Settings.php';
require_once __DIR__ . '/../../app/models/Application.php';

$auth = Auth::getInstance();
$auth->requireLogin();

$settings = new Settings();
$appModel = new Application();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Недействительный CSRF токен';
    } else {
        $action = $_POST['action'] ?? '';
        $id = $_POST['id'] ?? 0;

        if ($action === 'update_status' && $id) {
            $status = $_POST['status'] ?? 'new';
            if ($appModel->updateStatus($id, $status)) {
                $message = 'Статус заявки обновлен';
            } else {
                $error = 'Ошибка обновления статуса';
            }
        } elseif ($action === 'delete' && $id) {
            if ($appModel->delete($id)) {
                $message = 'Заявка удалена';
            } else {
                $error = 'Ошибка удаления';
            }
        }
    }
}

$applications = $appModel->getAll();
$csrf_token = $auth->generateCsrfToken();
$current_user = $auth->user();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки | Админ-панель</title>
    
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
                    <h1>Заявки с сайта</h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Дашборд</a> / Заявки
                    </div>
                </div>
                <!-- Кнопка добавления не нужна, но оставляем место для будущих действий -->
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

            <div class="admin-card">
                <div class="admin-card-header">
                    <h2><i class="fas fa-envelope"></i> Все заявки</h2>
                </div>
                <div class="admin-card-body">
                    <?php if (empty($applications)): ?>
                        <p style="text-align: center; color: var(--admin-gray); padding: 40px;">
                            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                            Нет заявок
                        </p>
                    <?php else: ?>
                        <div class="admin-table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">ID</th>
                                        <th style="width: 120px;">Дата</th>
                                        <th>Имя</th>
                                        <th>Телефон</th>
                                        <th>Адрес</th>
                                        <th>Описание работ</th>
                                        <th style="width: 120px;">Статус</th>
                                        <th style="width: 100px;">IP</th>
                                        <th style="width: 150px;">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $app): ?>
                                        <tr>
                                            <td>#<?= $app['id'] ?></td>
                                            <td><?= date('d.m.Y H:i', strtotime($app['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($app['name']) ?></td>
                                            <td><?= htmlspecialchars($app['phone']) ?></td>
                                            <td><?= htmlspecialchars($app['address'] ?? '') ?></td>
                                            <td><?= nl2br(htmlspecialchars(truncate($app['work_description'] ?? '', 50))) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $app['status'] ?>">
                                                    <?php 
                                                    $statuses = [
                                                        'new' => 'Новая',
                                                        'in_progress' => 'В работе',
                                                        'completed' => 'Завершена',
                                                        'rejected' => 'Отклонена'
                                                    ];
                                                    echo $statuses[$app['status']] ?? $app['status'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($app['ip_address'] ?? '') ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline" onclick="openEditModal(<?= $app['id'] ?>, '<?= $app['status'] ?>')" title="Изменить статус">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $app['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline" style="color: #EF4444;" onclick="return confirm('Удалить заявку?')" title="Удалить">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Модальное окно изменения статуса (стилизовано через admin.css) -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Изменить статус заявки</h3>
                <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" id="modalAppId">
                    <div class="form-group">
                        <label>Статус</label>
                        <select name="status" id="modalStatus" class="form-control">
                            <option value="new">Новая</option>
                            <option value="in_progress">В работе</option>
                            <option value="completed">Завершена</option>
                            <option value="rejected">Отклонена</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/assets/js/admin.js?v=<?= time() ?>"></script>
    <script>
        function openEditModal(id, currentStatus) {
            document.getElementById('modalAppId').value = id;
            document.getElementById('modalStatus').value = currentStatus;
            document.getElementById('statusModal').classList.add('active');
        }
        function closeModal() {
            document.getElementById('statusModal').classList.remove('active');
        }
        // Закрытие по клику вне модалки
        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>