<?php
// ==============================================
// admin/users.php - Управление пользователями
// ==============================================
session_start();
ob_start(); // Буферизация вывода

// Разрешаем eval для CKEditor (CSP)
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline';");

require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/Auth.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/models/Settings.php';
require_once __DIR__ . '/../../app/models/User.php';
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
$auth->requireAdmin(); // Только администратор может управлять пользователями

$settings = new Settings();
$userModel = new User();
$gallery = new Gallery();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// Обработка POST-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('Недействительный CSRF токен', 'error');
        ob_clean();
        header('Location: users.php' . ($action !== 'list' ? '?action=' . $action . ($id ? '&id=' . $id : '') : ''));
        exit;
    }

    $post_action = $_POST['action'] ?? '';

    // Создание пользователя
    if ($post_action === 'create') {
        // Проверка уникальности
        if ($userModel->isUsernameExists($_POST['username'])) {
            setFlash('Это имя пользователя уже занято', 'error');
        } elseif ($userModel->isEmailExists($_POST['email'])) {
            setFlash('Этот email уже используется', 'error');
        } else {
            $data = [
                'username' => $_POST['username'],
                'email' => $_POST['email'],
                'password' => $_POST['password'],
                'full_name' => $_POST['full_name'] ?? null,
                'role' => $_POST['role'],
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            if ($userModel->create($data)) {
                setFlash('Пользователь успешно добавлен!', 'success');
                $action = 'list';
            } else {
                setFlash('Ошибка при добавлении пользователя', 'error');
            }
        }
        ob_clean();
        header('Location: users.php' . ($action !== 'list' ? '?action=' . $action : ''));
        exit;
    }

    // Обновление пользователя
    if ($post_action === 'update' && $id) {
        $data = [
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'full_name' => $_POST['full_name'] ?? null,
            'role' => $_POST['role'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        // Если передан новый пароль, добавляем его в данные
        if (!empty($_POST['password'])) {
            $data['password'] = $_POST['password'];
        }

        // Проверка уникальности (исключая текущего пользователя)
        if ($userModel->isUsernameExists($data['username'], $id)) {
            setFlash('Это имя пользователя уже занято', 'error');
        } elseif ($userModel->isEmailExists($data['email'], $id)) {
            setFlash('Этот email уже используется', 'error');
        } else {
            if ($userModel->update($id, $data)) {
                setFlash('Пользователь успешно обновлен!', 'success');
                $action = 'list';
            } else {
                setFlash('Ошибка при обновлении пользователя', 'error');
            }
        }
        ob_clean();
        header('Location: users.php' . ($action !== 'list' ? '?action=' . $action : ''));
        exit;
    }

    // Удаление пользователя
    if ($post_action === 'delete' && isset($_POST['id'])) {
        if ($userModel->delete($_POST['id'])) {
            setFlash('Пользователь успешно удален!', 'success');
        } else {
            setFlash('Нельзя удалить последнего администратора', 'error');
        }
        ob_clean();
        header('Location: users.php');
        exit;
    }
}

// Получаем список пользователей
$users = $userModel->getAll();
$user = $id ? $userModel->getById($id) : null;
$current_user = $auth->user();
$csrf_token = $auth->generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление пользователями | Админ-панель</title>
    
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
                    <h1>
                        <?php if ($action === 'create'): ?>
                            Добавление пользователя
                        <?php elseif ($action === 'edit' && $user): ?>
                            Редактирование: <?= htmlspecialchars($user['username']) ?>
                        <?php else: ?>
                            Управление пользователями
                        <?php endif; ?>
                    </h1>
                    <div class="admin-breadcrumb">
                        <a href="dashboard.php">Дашборд</a> / 
                        <a href="users.php">Пользователи</a> /
                        <?= $action === 'create' ? 'Добавление' : ($action === 'edit' ? 'Редактирование' : 'Список') ?>
                    </div>
                </div>
                <?php if ($action === 'list'): ?>
                    <div class="admin-actions">
                        <a href="?action=create" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Добавить пользователя
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php $flash = getFlash(); ?>
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <!-- Список пользователей -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2><i class="fas fa-users"></i> Все пользователи</h2>
                    </div>
                    <div class="admin-card-body">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Пользователь</th>
                                    <th>Email</th>
                                    <th>Роль</th>
                                    <th>Статус</th>
                                    <th>Последний вход</th>
                                    <th>Дата регистрации</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $item): ?>
                                    <tr>
                                        <td>#<?= $item['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['username']) ?></strong>
                                            <?php if ($item['full_name']): ?>
                                                <br><small><?= htmlspecialchars($item['full_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($item['email']) ?></td>
                                        <td>
                                            <?php if ($item['role'] === 'admin'): ?>
                                                <span style="color: #0B3B5C; font-weight: 600;">Администратор</span>
                                            <?php else: ?>
                                                <span style="color: #64748B;">Редактор</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['is_active']): ?>
                                                <span style="color: #10B981;"><i class="fas fa-circle" style="font-size:0.5rem;"></i> Активен</span>
                                            <?php else: ?>
                                                <span style="color: #EF4444;"><i class="fas fa-circle" style="font-size:0.5rem;"></i> Заблокирован</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $item['last_login'] ? format_date($item['last_login'], 'd.m.Y H:i') : '—' ?></td>
                                        <td><?= format_date($item['created_at'], 'd.m.Y') ?></td>
                                        <td>
                                            <a href="?action=edit&id=<?= $item['id'] ?>" class="btn btn-sm btn-outline" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($item['id'] != $current_user['id']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline" style="color: #EF4444;" 
                                                            onclick="return confirm('Вы уверены, что хотите удалить этого пользователя?')" title="Удалить">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($action === 'create' || ($action === 'edit' && $user)): ?>
                <!-- Форма добавления/редактирования -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2><i class="fas <?= $action === 'create' ? 'fa-user-plus' : 'fa-user-edit' ?>"></i> <?= $action === 'create' ? 'Новый пользователь' : 'Редактирование пользователя' ?></h2>
                    </div>
                    <div class="admin-card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Имя пользователя *</label>
                                    <input type="text" name="username" class="form-control" required
                                           value="<?= htmlspecialchars($user['username'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" name="email" class="form-control" required
                                           value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Полное имя</label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                            </div>

                            <?php if ($action === 'create' || !empty($_GET['change_password'])): ?>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Пароль <?= $action === 'create' ? '*' : '' ?></label>
                                        <input type="password" name="password" class="form-control" <?= $action === 'create' ? 'required' : '' ?>>
                                        <?php if ($action === 'edit'): ?>
                                            <small style="color: var(--admin-gray);">Оставьте пустым, чтобы не изменять</small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($action === 'create'): ?>
                                        <div class="form-group">
                                            <label>Подтверждение пароля *</label>
                                            <input type="password" name="confirm_password" class="form-control" required>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Роль</label>
                                    <select name="role" class="form-control">
                                        <option value="editor" <?= ($user['role'] ?? '') === 'editor' ? 'selected' : '' ?>>Редактор</option>
                                        <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Администратор</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Статус</label>
                                    <div class="form-checkbox" style="margin-top:10px;">
                                        <input type="checkbox" name="is_active" id="is_active" <?= !isset($user['is_active']) || $user['is_active'] ? 'checked' : '' ?>>
                                        <label for="is_active">Активен</label>
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; gap: 15px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?= $action === 'create' ? 'Создать пользователя' : 'Сохранить изменения' ?>
                                </button>
                                <a href="users.php" class="btn btn-outline"><i class="fas fa-times"></i> Отмена</a>
                                <?php if ($action === 'edit' && $user['id'] != $current_user['id']): ?>
                                    <a href="?action=edit&id=<?= $user['id'] ?>&change_password=1" class="btn btn-outline"><i class="fas fa-key"></i> Изменить пароль</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/assets/js/admin.js?v=<?= time() ?>"></script>

    <?php if ($action === 'create'): ?>
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            var password = document.querySelector('input[name="password"]').value;
            var confirm = document.querySelector('input[name="confirm_password"]').value;
            if (password !== confirm) {
                e.preventDefault();
                alert('Пароли не совпадают!');
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
<?php ob_end_flush(); ?>