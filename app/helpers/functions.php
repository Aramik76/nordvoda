<?php
// ==============================================
// functions.php - Вспомогательные функции
// ==============================================

if (!function_exists('dd')) {
    function dd($var) {
        echo '<pre style="background: #1E293B; color: #E2E8F0; padding: 20px; border-radius: 8px; margin: 20px; overflow: auto;">';
        print_r($var);
        echo '</pre>';
        die();
    }
}

if (!function_exists('dump')) {
    function dump($var) {
        echo '<pre style="background: #F1F5F9; color: #0F172A; padding: 15px; border-radius: 6px; margin: 10px; overflow: auto;">';
        print_r($var);
        echo '</pre>';
    }
}

if (!function_exists('format_phone')) {
    function format_phone($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (strlen($phone) == 11) {
            $phone = '+' . substr($phone, 0, 1) . ' (' . substr($phone, 1, 3) . ') ' . 
                     substr($phone, 4, 3) . '-' . substr($phone, 7, 2) . '-' . substr($phone, 9, 2);
        } elseif (strlen($phone) == 12) {
            $phone = '+' . substr($phone, 0, 2) . ' (' . substr($phone, 2, 3) . ') ' . 
                     substr($phone, 5, 3) . '-' . substr($phone, 8, 2) . '-' . substr($phone, 10, 2);
        }
        
        return $phone;
    }
}

if (!function_exists('truncate')) {
    function truncate($text, $length = 100, $suffix = '...') {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length) . $suffix;
    }
}

if (!function_exists('slugify')) {
    function slugify($string) {
        $string = mb_strtolower($string, 'UTF-8');
        $string = str_replace([
            'а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п',
            'р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я',
            ' ', '/', '\\', '?', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')',
            '+', '=', '{', '}', '[', ']', '|', ';', ':', '"', "'", ',', '.', '<', '>'
        ], [
            'a','b','v','g','d','e','e','zh','z','i','y','k','l','m','n','o','p',
            'r','s','t','u','f','h','ts','ch','sh','sch','','y','','e','yu','ya',
            '-', '-', '-', '', '', '', '', '', '', '', '', '', '', '', '',
            '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''
        ], $string);
        $string = preg_replace('/-+/', '-', $string);
        $string = trim($string, '-');
        return $string;
    }
}

if (!function_exists('get_gravatar')) {
    function get_gravatar($email, $size = 80) {
        $hash = md5(strtolower(trim($email)));
        return "https://www.gravatar.com/avatar/$hash?s=$size&d=mp";
    }
}

if (!function_exists('format_date')) {
    function format_date($date, $format = 'd.m.Y') {
        if (empty($date)) return '';
        $timestamp = strtotime($date);
        return date($format, $timestamp);
    }
}

if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        $timestamp = strtotime($datetime);
        $now = time();
        $diff = $now - $timestamp;
        
        if ($diff < 60) {
            return 'только что';
        } elseif ($diff < 3600) {
            $min = floor($diff / 60);
            return $min . ' ' . plural($min, ['минуту', 'минуты', 'минут']) . ' назад';
        } elseif ($diff < 86400) {
            $hour = floor($diff / 3600);
            return $hour . ' ' . plural($hour, ['час', 'часа', 'часов']) . ' назад';
        } elseif ($diff < 2592000) {
            $day = floor($diff / 86400);
            return $day . ' ' . plural($day, ['день', 'дня', 'дней']) . ' назад';
        } else {
            return date('d.m.Y', $timestamp);
        }
    }
}

if (!function_exists('plural')) {
    function plural($n, $forms) {
        return $n % 10 == 1 && $n % 100 != 11 ? $forms[0] :
              ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? $forms[1] : $forms[2]);
    }
}

if (!function_exists('get_file_icon')) {
    function get_file_icon($mime_type) {
        $icons = [
            'image/' => 'fa-file-image',
            'video/' => 'fa-file-video',
            'audio/' => 'fa-file-audio',
            'application/pdf' => 'fa-file-pdf',
            'application/msword' => 'fa-file-word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fa-file-word',
            'application/vnd.ms-excel' => 'fa-file-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'fa-file-excel',
            'application/zip' => 'fa-file-archive',
            'application/x-rar-compressed' => 'fa-file-archive',
            'text/plain' => 'fa-file-alt',
            'text/html' => 'fa-file-code',
            'application/javascript' => 'fa-file-code',
            'text/css' => 'fa-file-code'
        ];
        
        foreach ($icons as $pattern => $icon) {
            if (strpos($mime_type, $pattern) === 0 || $mime_type == $pattern) {
                return $icon;
            }
        }
        
        return 'fa-file';
    }
}

if (!function_exists('get_file_size')) {
    function get_file_size($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('clean_string')) {
    function clean_string($string) {
        $string = strip_tags($string);
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        return trim($string);
    }
}

if (!function_exists('generate_password')) {
    function generate_password($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        $max = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        
        return $password;
    }
}

if (!function_exists('is_ajax_request')) {
    function is_ajax_request() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
}

if (!function_exists('redirect')) {
    function redirect($url, $statusCode = 302) {
        header('Location: ' . $url, true, $statusCode);
        exit;
    }
}

if (!function_exists('asset')) {
    function asset($path) {
        return '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    function url($path = '') {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . $host . '/' . ltrim($path, '/');
    }
}
?>