<?php
// ==============================================
// Settings.php - Управление настройками сайта
// ==============================================
require_once __DIR__ . '/../config/db.php';

class Settings {
    private $conn;
    private $table = 'settings';
    private $cache = [];
    private $initialized = false;

    public function __construct() {
        try {
            $database = new Database();
            $this->conn = $database->getConnection();
            $this->loadAllSettings();
            $this->initialized = true;
        } catch(Exception $e) {
            error_log("[SETTINGS] Init error: " . $e->getMessage());
        }
    }

    private function loadAllSettings() {
        try {
            $query = "SELECT `key`, `value` FROM " . $this->table;
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->cache[$row['key']] = $row['value'];
            }
        } catch(PDOException $e) {
            error_log("[SETTINGS] Load error: " . $e->getMessage());
        }
    }

    public function get($key, $default = '') {
        if (!$this->initialized) {
            return $default;
        }
        // Если ключа нет в кэше, пробуем загрузить из БД или создать
        if (!isset($this->cache[$key])) {
            try {
                $query = "SELECT `value` FROM " . $this->table . " WHERE `key` = :key";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $this->cache[$key] = $row['value'];
                } else {
                    // Записи нет – создаём со значением по умолчанию
                    $this->add($key, $default, 'text', 'general', $key);
                    $this->cache[$key] = $default;
                }
            } catch(PDOException $e) {
                error_log("[SETTINGS] Get error: " . $e->getMessage());
                return $default;
            }
        }
        return isset($this->cache[$key]) && $this->cache[$key] !== '' ? $this->cache[$key] : $default;
    }

    public function getAll() {
        return $this->cache;
    }

    public function getGroup($group) {
        try {
            $query = "SELECT `key`, `value` FROM " . $this->table . " WHERE `group` = :group";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':group', $group);
            $stmt->execute();
            
            $result = [];
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $result[$row['key']] = $row['value'];
            }
            return $result;
        } catch(PDOException $e) {
            error_log("[SETTINGS] Get group error: " . $e->getMessage());
            return [];
        }
    }

    public function set($key, $value) {
        try {
            // Пытаемся обновить существующую запись
            $query = "UPDATE " . $this->table . " SET `value` = :value, updated_at = NOW() WHERE `key` = :key";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $value);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                // Запись не существовала – вставляем новую
                // Определяем группу по префиксу ключа (для удобства)
                $group = 'general';
                if (strpos($key, 'watermark_') === 0) $group = 'watermark';
                elseif (strpos($key, 'social_') === 0) $group = 'social';
                elseif (strpos($key, 'contact_') === 0) $group = 'contacts';
                elseif (strpos($key, 'hero_') === 0 || strpos($key, 'services_') === 0 || strpos($key, 'projects_') === 0 || strpos($key, 'reviews_') === 0) $group = 'home';
                elseif (strpos($key, 'about_') === 0) $group = 'about';
                elseif (strpos($key, 'footer_') === 0) $group = 'footer';
                elseif (strpos($key, 'form_') === 0) $group = 'form';
                elseif (strpos($key, 'telegram_') === 0) $group = 'telegram';
                elseif (strpos($key, 'seo_') === 0) $group = 'seo';

                $query = "INSERT INTO " . $this->table . " (`key`, `value`, `type`, `group`, `label`) 
                          VALUES (:key, :value, 'text', :group, :key)";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':key', $key);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':group', $group);
                $stmt->execute();
            }
            
            $this->cache[$key] = $value;
            return true;
        } catch(PDOException $e) {
            error_log("[SETTINGS] Set error: " . $e->getMessage());
            return false;
        }
    }

    public function add($key, $value, $type = 'text', $group = 'general', $label = '') {
        try {
            $query = "INSERT INTO " . $this->table . " 
                      (`key`, `value`, `type`, `group`, `label`) 
                      VALUES (:key, :value, :type, :group, :label)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':group', $group);
            $stmt->bindParam(':label', $label);
            
            if($stmt->execute()) {
                $this->cache[$key] = $value;
                return $this->conn->lastInsertId();
            }
        } catch(PDOException $e) {
            error_log("[SETTINGS] Add error: " . $e->getMessage());
        }
        return false;
    }

    public function delete($key) {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE `key` = :key";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':key', $key);
            
            if($stmt->execute()) {
                unset($this->cache[$key]);
                return true;
            }
        } catch(PDOException $e) {
            error_log("[SETTINGS] Delete error: " . $e->getMessage());
        }
        return false;
    }

    public function refresh() {
        $this->cache = [];
        $this->loadAllSettings();
    }

    public function isInitialized() {
        return $this->initialized;
    }
}
?>