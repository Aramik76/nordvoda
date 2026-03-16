<?php
// ==============================================
// admin/index.php - Вход в админ-панель
// ==============================================
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Разрешаем eval для CKEditor (CSP)
header("Content-Security-Policy: script-src 'self' 'unsafe-eval' 'unsafe-inline';");

require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/Auth.php';
require_once __DIR__ . '/../../app/models/Settings.php';

$auth = Auth::getInstance();
$settings = new Settings();

// Если уже авторизован, перенаправляем на дашборд
if ($auth->check()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Пожалуйста, заполните все поля';
    } else {
        if ($auth->attempt($username, $password)) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Неверный логин или пароль';
        }
    }
}

// Генерируем CSRF токен
$csrf_token = $auth->generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в админ-панель | <?= htmlspecialchars($settings->get('site_name', 'БурСервис')) ?></title>
    
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0B3B5C 0%, #1A5F7A 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            line-height: 0;
            opacity: 0.1;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
            position: relative;
            z-index: 10;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 30px;
            text-decoration: none;
        }
        
        .logo img {
            height: 50px;
            width: auto;
        }
        
        .logo span {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0B3B5C;
        }
        
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1E293B;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .subtitle {
            color: #64748B;
            text-align: center;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1E293B;
            margin-bottom: 8px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 1.1rem;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #F8FAFC;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0B3B5C;
            background: white;
            box-shadow: 0 0 0 4px rgba(11,59,92,0.1);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: #0B3B5C;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn:hover {
            background: #072A44;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(11,59,92,0.2);
        }
        
        .btn i {
            font-size: 1.1rem;
        }
        
        .alert {
            background: #FEE2E2;
            color: #991B1B;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #FECACA;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .remember-me input {
            width: 18px;
            height: 18px;
            accent-color: #0B3B5C;
        }
        
        .remember-me label {
            font-size: 0.9rem;
            color: #64748B;
        }
        
        .footer-links {
            margin-top: 25px;
            text-align: center;
            font-size: 0.875rem;
        }
        
        .footer-links a {
            color: #64748B;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: #0B3B5C;
        }
        
        .divider {
            margin: 0 10px;
            color: #E2E8F0;
        }
        
        .demo-credentials {
            margin-top: 25px;
            padding: 15px;
            background: #F8FAFC;
            border-radius: 12px;
            border: 1px dashed #E2E8F0;
            font-size: 0.875rem;
        }
        
        .demo-credentials p {
            color: #64748B;
            margin-bottom: 8px;
        }
        
        .demo-credentials span {
            color: #0B3B5C;
            font-weight: 600;
        }
        
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Декоративная волна -->
    <div class="wave">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
            <path fill="#FFFFFF" fill-opacity="1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
        </svg>
    </div>
    
    <div class="login-container">
        <div class="login-card">

            
            <h2>Вход в админ-панель</h2>
            
            
            <?php if ($error): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="form-group">
                    <label>Логин или Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" class="form-control" 
                               placeholder="admin@example.com" 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                               required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Пароль</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-control" 
                               placeholder="••••••••" required>
                    </div>
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Запомнить меня</label>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Войти в систему
                </button>
            </form>
        
            
            <div class="footer-links">
                <a href="/"><i class="fas fa-home"></i> Вернуться на сайт</a>
                <span class="divider">|</span>
                <a href="#"><i class="fas fa-key"></i> Забыли пароль?</a>
            </div>
        </div>
    </div>
    
    <script>
        // Добавляем эффект появления
        document.addEventListener('DOMContentLoaded', function() {
            const card = document.querySelector('.login-card');
            card.style.opacity = '0';
            setTimeout(() => {
                card.style.opacity = '1';
            }, 100);
        });
    </script>
</body>
</html>