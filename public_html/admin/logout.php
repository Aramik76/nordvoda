<?php
// ==============================================
// admin/logout.php - Выход из системы
// ==============================================
session_start();
require_once __DIR__ . '/../../app/helpers/Auth.php';
// Разрешаем eval для CKEditor (CSP)
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline';");

$auth = Auth::getInstance();
$auth->logout();

// Перенаправляем на страницу входа
header('Location: index.php');
exit;