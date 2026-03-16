<?php
// ==============================================
// 404.php - Страница ошибки 404
// ==============================================
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Страница не найдена</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0B3B5C 0%, #1A5F7A 100%);
            color: white;
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .container {
            max-width: 600px;
            padding: 40px;
        }
        h1 { font-size: 120px; margin: 0; color: #FAB13B; }
        h2 { font-size: 32px; margin: 20px 0; }
        p { font-size: 18px; opacity: 0.9; margin-bottom: 30px; }
        .btn {
            display: inline-block;
            background: white;
            color: #0B3B5C;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <h2>Страница не найдена</h2>
        <p>Извините, запрашиваемая страница не существует или была перемещена.</p>
        <a href="/" class="btn">Вернуться на главную</a>
    </div>
</body>
</html>