<?php
// ==============================================
// admin/profile.php - Профиль пользователя (финальная версия)
// ==============================================
session_start();
ob_start();

// Разрешаем eval для CKEditor (CSP)
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline';");

require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/Auth.php';
require_once __DIR__ . '/../../app/helpers/functions.php';
require_once __DIR__ . '/../../app/models/Settings.php';
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/models/Gallery.php';

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
$userModel = new User();
$gallery = new Gallery();

$current_user = $auth->user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$auth->verifyCsrfToken($_POST['csrf_token'])) {
        setFlash('Недействительный CSRF токен', 'error');
        ob_clean();
        header('Location: profile.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        // Логирование для отладки
        file_put_contents(__DIR__ . '/profile_debug.log', date('Y-m-d H:i:s') . " - POST: " . print_r($_POST, true) . "\n", FILE_APPEND);

        $data = [
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
            'full_name' => $_POST['full_name'] ?? ''
        ];

        if (empty($data['username']) || empty($data['email'])) {
            setFlash('Имя пользователя и email обязательны', 'error');
        } else {
            if ($userModel->isUsernameExists($data['username'], $current_user['id'])) {
                setFlash('Это имя пользователя уже занято', 'error');
            } elseif ($userModel->isEmailExists($data['email'], $current_user['id'])) {
                setFlash('Этот email уже используется', 'error');
            } else {
                if ($userModel->update($current_user['id'], $data)) {
                    $_SESSION['user_name'] = $data['full_name'] ?: $data['username'];
                    setFlash('Профиль успешно обновлен!', 'success');
                } else {
                    setFlash('Ошибка при обновлении профиля', 'error');
                }
            }
        }
        ob_clean();
        header('Location: profile.php');
        exit;
    }

    if ($action === 'password') {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            setFlash('Заполните все поля', 'error');
        } elseif ($new_password !== $confirm_password) {
            setFlash('Новый пароль и подтверждение не совпадают', 'error');
        } elseif (strlen($new_password) < 6) {
            setFlash('Пароль должен быть не менее 6 символов', 'error');
        } else {
            if ($userModel->changePassword($current_user['id'], $old_password, $new_password)) {
                setFlash('Пароль успешно изменен!', 'success');
            } else {
                setFlash('Неверный текущий пароль', 'error');
            }
        }
        ob_clean();
        header('Location: profile.php');
        exit;
    }
}

$current_user = $userModel->getById($current_user['id']);
$csrf_token = $auth->generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мой профиль | Админ-панель</title>
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= time() ?>">
    <style>
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
            padding: 30px;
            background: linear-gradient(135deg, #0B3B5C 0%, #1A5F7A 100%);
            border-radius: 16px;
            color: white;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
        }
        .profile-info h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        .profile-info p {
            opacity: 0.9;
        }
        .profile-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--admin-border);
            padding-bottom: 15px;
        }
        .profile-tab {
            padding: 12px 24px;
            background: white;
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .profile-tab:hover {
            background: #F8FAFC;
            border-color: var(--admin-primary);
        }
        .profile-tab.active {
            background: var(--admin-primary);
            color: white;
            border-color: var(--admin-primary);
        }
        .profile-section {
            display: none;
        }
        .profile-section.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header">
                <div class="admin-title">
                    <h1>Мой профиль</h1>
                    <div class="admin-breadcrumb"><a href="dashboard.php">Дашборд</a> / Профиль</div>
                </div>
            </div>

            <?php $flash = getFlash(); ?>
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <div class="profile-header">
                <?php 
                $avatar = $current_user['avatar_id'] ? $gallery->getById($current_user['avatar_id']) : null;
                $avatar_url = $avatar ? $avatar['filepath'] : '/assets/img/admin-avatar.png';
                ?>
                <img src="<?= htmlspecialchars($avatar_url) ?>" alt="Avatar" class="profile-avatar">
                <div class="profile-info">
                    <h2><?= htmlspecialchars($current_user['full_name'] ?? $current_user['username']) ?></h2>
                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($current_user['email']) ?></p>
                    <p><i class="fas fa-calendar"></i> На сайте с <?= format_date($current_user['created_at'], 'd.m.Y') ?></p>
                </div>
            </div>

            <div class="profile-tabs">
                <div class="profile-tab active" data-tab="profile"><i class="fas fa-user"></i> Личные данные</div>
                <div class="profile-tab" data-tab="security"><i class="fas fa-shield-alt"></i> Безопасность</div>
                <div class="profile-tab" data-tab="activity"><i class="fas fa-history"></i> Активность</div>
            </div>

            <!-- Личные данные -->
            <div class="profile-section active" id="tab-profile">
                <div class="admin-card">
                    <div class="admin-card-header"><h2>Редактирование профиля</h2></div>
                    <div class="admin-card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="profile">
                            <div class="form-group">
                                <label>Имя пользователя *</label>
                                <input type="text" name="username" class="form-control" required
                                       value="<?= htmlspecialchars($current_user['username']) ?>"
                                       autocomplete="username">
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" class="form-control" required
                                       value="<?= htmlspecialchars($current_user['email']) ?>"
                                       autocomplete="email">
                            </div>
                            <div class="form-group">
                                <label>Полное имя</label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?= htmlspecialchars($current_user['full_name'] ?? '') ?>"
                                       autocomplete="name">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить изменения</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Безопасность -->
            <div class="profile-section" id="tab-security">
                <div class="admin-card">
                    <div class="admin-card-header"><h2>Изменение пароля</h2></div>
                    <div class="admin-card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="action" value="password">
                            <!-- Скрытое поле с именем пользователя для менеджеров паролей -->
                            <input type="hidden" name="username" value="<?= htmlspecialchars($current_user['username']) ?>">

                            <div class="form-group">
                                <label>Текущий пароль *</label>
                                <input type="password" name="old_password" class="form-control" required
                                       autocomplete="current-password">
                            </div>
                            <div class="form-group">
                                <label>Новый пароль *</label>
                                <input type="password" name="new_password" class="form-control" required
                                       autocomplete="new-password">
                                <small>Минимум 6 символов</small>
                            </div>
                            <div class="form-group">
                                <label>Подтверждение пароля *</label>
                                <input type="password" name="confirm_password" class="form-control" required
                                       autocomplete="off">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Изменить пароль</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Активность -->
            <div class="profile-section" id="tab-activity">
                <div class="admin-card">
                    <div class="admin-card-header"><h2>История активности</h2></div>
                    <div class="admin-card-body">
                        <div class="activity-list">
                            <div class="activity-item">
                                <div class="activity-icon"><i class="fas fa-sign-in-alt"></i></div>
                                <div class="activity-content">
                                    <div class="activity-title">Последний вход в систему</div>
                                    <div class="activity-time"><?= $current_user['last_login'] ? format_date($current_user['last_login'], 'd.m.Y H:i:s') : 'Первый вход' ?></div>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon"><i class="fas fa-user-plus"></i></div>
                                <div class="activity-content">
                                    <div class="activity-title">Дата регистрации</div>
                                    <div class="activity-time"><?= format_date($current_user['created_at'], 'd.m.Y H:i:s') ?></div>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon"><i class="fas fa-id-badge"></i></div>
                                <div class="activity-content">
                                    <div class="activity-title">Роль в системе</div>
                                    <div class="activity-time"><?= $current_user['role'] === 'admin' ? 'Администратор' : 'Редактор' ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/assets/js/admin.js?v=<?= time() ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.profile-tab');
            const sections = document.querySelectorAll('.profile-section');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.dataset.tab;
                    tabs.forEach(t => t.classList.remove('active'));
                    sections.forEach(s => s.classList.remove('active'));
                    this.classList.add('active');
                    document.getElementById(`tab-${tabId}`).classList.add('active');
                });
            });
            const passwordForm = document.querySelector('#tab-security form');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPass = this.querySelector('[name="new_password"]').value;
                    const confirmPass = this.querySelector('[name="confirm_password"]').value;
                    if (newPass !== confirmPass) {
                        e.preventDefault();
                        alert('Новый пароль и подтверждение не совпадают');
                    }
                });
            }
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>