<?php
// admin/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
// Разрешаем eval для CKEditor (CSP)
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline';");

?>
<aside class="admin-sidebar">
    <div class="admin-sidebar-header">
        <a href="dashboard.php" class="admin-logo">
            <img src="<?= htmlspecialchars($settings->get('site_logo', '/assets/img/logo.png')) ?>" alt="logo">
            <span><?= htmlspecialchars($settings->get('site_name', 'БурСервис')) ?></span>
        </a>
    </div>
    <div class="admin-user">
        <img src="/assets/img/admin-avatar.png" alt="avatar" class="admin-avatar">
        <div class="admin-user-info">
            <h4><?= htmlspecialchars($current_user['full_name'] ?? $current_user['username']) ?></h4>
            <p><?= $current_user['role'] == 'admin' ? 'Администратор' : 'Редактор' ?></p>
        </div>
    </div>
    <nav class="admin-nav">
        <ul>
            <li><a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt"></i> Дашборд</a></li>
            <li><a href="applications.php" class="<?= $current_page == 'applications.php' ? 'active' : '' ?>"><i class="fas fa-envelope"></i> Заявки</a></li>

            <li><a href="services.php" class="<?= $current_page == 'services.php' ? 'active' : '' ?>"><i class="fas fa-water"></i> Услуги</a></li>

            <li><a href="projects.php" class="<?= $current_page == 'projects.php' ? 'active' : '' ?>"><i class="fas fa-hard-hat"></i> Проекты</a></li>
            <li><a href="reviews.php" class="<?= $current_page == 'reviews.php' ? 'active' : '' ?>"><i class="fas fa-star"></i> Отзывы</a></li>
            <li><a href="gallery.php" class="<?= $current_page == 'gallery.php' ? 'active' : '' ?>"><i class="fas fa-images"></i> Медиабиблиотека</a></li>
            <li><a href="settings.php" class="<?= $current_page == 'settings.php' ? 'active' : '' ?>"><i class="fas fa-cog"></i> Настройки сайта</a></li>

            <li><a href="users.php" class="<?= $current_page == 'users.php' ? 'active' : '' ?>"><i class="fas fa-users"></i> Пользователи</a></li>
            <li><a href="profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>"><i class="fas fa-user-circle"></i> Мой профиль</a></li>
            <li><a href="logout.php" style="color: #EF4444;"><i class="fas fa-sign-out-alt"></i> Выход</a></li>
        </ul>
    </nav>
</aside>