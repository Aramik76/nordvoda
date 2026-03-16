<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// НАСТРОЙТЕ ПОДКЛЮЧЕНИЕ К БД
$db_config = [
    'host' => 'localhost',
    'dbname' => 'aramserg_nordvoda',
    'user' => 'aramserg_nordvoda',
    'password' => 'VV8cvN3e',
    'charset' => 'utf8mb4'
];

// СЕКРЕТНЫЙ КЛЮЧ ДЛЯ ДОСТУПА К СТАТИСТИКЕ (придумайте свой)
define('ADMIN_SECRET_KEY', 'pos_xcz_wiL_!23'); // <-- ИЗМЕНИТЕ ЭТО

try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}",
        $db_config['user'],
        $db_config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    function getClientIP() {
        $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Сохранение согласия
        if (isset($input['action']) && $input['action'] === 'save_consent') {
            $ip = getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $consentType = $input['consent'] ?? 'rejected';

            // Вставляем или обновляем запись
            $stmt = $pdo->prepare("INSERT INTO cookie_consents (ip_address, user_agent, consent_type, consent_date, page_url) 
                                    VALUES (?, ?, ?, NOW(), ?)
                                    ON DUPLICATE KEY UPDATE 
                                    consent_type = VALUES(consent_type), consent_date = VALUES(consent_date), user_agent = VALUES(user_agent)");
            $stmt->execute([$ip, $userAgent, $consentType, $_SERVER['HTTP_REFERER'] ?? '/']);

            echo json_encode(['success' => true, 'ip' => $ip]);
        }
        // Получение статистики (только с правильным ключом)
        elseif (isset($input['action']) && $input['action'] === 'get_statistics') {
            if (!isset($input['admin_key']) || $input['admin_key'] !== ADMIN_SECRET_KEY) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
                exit;
            }

            $period = $input['period'] ?? 'week';
            $sql = "SELECT DATE(consent_date) as stat_date, 
                           COUNT(*) as total_visits,
                           SUM(CASE WHEN consent_type = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
                           SUM(CASE WHEN consent_type = 'rejected' THEN 1 ELSE 0 END) as rejected_count
                    FROM cookie_consents ";

            if ($period === 'today') {
                $sql .= "WHERE DATE(consent_date) = CURDATE() ";
            } elseif ($period === 'week') {
                $sql .= "WHERE consent_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) ";
            } elseif ($period === 'month') {
                $sql .= "WHERE consent_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) ";
            }

            $sql .= "GROUP BY DATE(consent_date) ORDER BY stat_date DESC";
            $stmt = $pdo->query($sql);
            $statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total = $pdo->query("SELECT COUNT(*) as total_consents,
                                         SUM(CASE WHEN consent_type='accepted' THEN 1 ELSE 0 END) as total_accepted,
                                         SUM(CASE WHEN consent_type='rejected' THEN 1 ELSE 0 END) as total_rejected
                                  FROM cookie_consents")->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'statistics' => $statistics, 'totals' => $total]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['status' => 'ok']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}