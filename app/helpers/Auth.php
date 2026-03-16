<?php
// ==============================================
// Auth.php - Класс авторизации
// ==============================================
require_once __DIR__ . '/../models/User.php';

class Auth {
    private static $instance = null;
    private $user = null;
    
    private function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->checkSession();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function checkSession() {
        if (isset($_SESSION['user_id'])) {
            $userModel = new User();
            $this->user = $userModel->getById($_SESSION['user_id']);
            
            if (!$this->user || !$this->user['is_active']) {
                $this->logout();
            }
        }
    }
    
    public function attempt($username, $password) {
        $userModel = new User();
        $user = $userModel->authenticate($username, $password);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'] ?: $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            $this->user = $user;
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        unset($_SESSION['user_id']);
        unset($_SESSION['user_name']);
        unset($_SESSION['user_role']);
        unset($_SESSION['login_time']);
        session_destroy();
        $this->user = null;
    }
    
    public function check() {
        return $this->user !== null;
    }
    
    public function user() {
        return $this->user;
    }
    
    public function id() {
        return $this->user ? $this->user['id'] : null;
    }
    
    public function isAdmin() {
        return $this->user && $this->user['role'] === 'admin';
    }
    
    public function isEditor() {
        return $this->user && in_array($this->user['role'], ['admin', 'editor']);
    }
    
    public function requireLogin() {
        if (!$this->check()) {
            header('Location: /admin/');
            exit;
        }
    }
    
    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header('Location: /admin/dashboard.php?error=access_denied');
            exit;
        }
    }
    
    public function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function sessionExpired($max_lifetime = 3600) {
        if (isset($_SESSION['login_time'])) {
            return (time() - $_SESSION['login_time']) > $max_lifetime;
        }
        return true;
    }
    
    public function refreshSession() {
        $_SESSION['login_time'] = time();
    }
}
?>