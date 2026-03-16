<?php
// public_html/test.php
echo "<h1>Проверка структуры сайта</h1>";

echo "<h2>Текущая директория:</h2>";
echo __DIR__ . "<br><br>";

echo "<h2>Проверка наличия файлов:</h2>";
$files_to_check = [
    '/assets/css/style.css',
    '/assets/css/responsive.css',
    '/assets/js/main.js',
    '/assets/js/animations.js',
    '/assets/img/logo.png',
    '/assets/img/hero-drill.png'
];

foreach($files_to_check as $file) {
    $full_path = __DIR__ . $file;
    if (file_exists($full_path)) {
        echo "✅ $file - найден<br>";
    } else {
        echo "❌ $file - НЕ НАЙДЕН! Путь: $full_path<br>";
    }
}

echo "<h2>Проверка прав доступа:</h2>";
$dirs_to_check = [
    '/assets',
    '/assets/css',
    '/assets/js',
    '/assets/img',
    '/uploads'
];

foreach($dirs_to_check as $dir) {
    $full_path = __DIR__ . $dir;
    if (is_dir($full_path)) {
        $perms = substr(sprintf('%o', fileperms($full_path)), -4);
        echo "📁 $dir - права: $perms<br>";
    } else {
        echo "❌ $dir - директория не существует!<br>";
    }
}

echo "<h2>Информация о сервере:</h2>";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "SCRIPT_FILENAME: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "<br>";
?>