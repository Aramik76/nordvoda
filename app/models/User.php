<?php
// ==============================================
// User.php - Управление пользователями
// ==============================================
require_once __DIR__ . '/../config/db.php';

class User {
    private $conn;
    private $table = 'users';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Аутентификация пользователя по username/email и паролю
     * @param string $username
     * @param string $password
     * @return array|false
     */
    public function authenticate($username, $password) {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                      WHERE (username = :username OR email = :username) 
                      AND is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Обновляем время последнего входа
                $this->updateLastLogin($user['id']);
                // Удаляем хеш пароля из результата
                unset($user['password_hash']);
                return $user;
            }
        } catch(PDOException $e) {
            error_log("[USER] Authenticate error: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Получить пользователя по ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        try {
            $query = "SELECT id, username, email, full_name, avatar_id, role, is_active, last_login, created_at 
                      FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("[USER] Get by id error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Получить всех пользователей
     * @return array
     */
    public function getAll() {
        try {
            $query = "SELECT id, username, email, full_name, role, is_active, last_login, created_at 
                      FROM " . $this->table . " 
                      ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("[USER] Get all error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Создать нового пользователя
     * @param array $data
     * @return bool
     */
    public function create($data) {
        try {
            $query = "INSERT INTO " . $this->table . " 
                      (username, email, password_hash, full_name, avatar_id, role, is_active) 
                      VALUES (:username, :email, :password_hash, :full_name, :avatar_id, :role, :is_active)";
            
            $stmt = $this->conn->prepare($query);
            
            return $stmt->execute([
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                ':full_name' => $data['full_name'] ?? null,
                ':avatar_id' => $data['avatar_id'] ?? null,
                ':role' => $data['role'] ?? 'editor',
                ':is_active' => $data['is_active'] ?? 1
            ]);
        } catch(PDOException $e) {
            error_log("[USER] Create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Обновить данные пользователя
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        try {
            $query = "UPDATE " . $this->table . " 
                      SET username = :username, email = :email, 
                          full_name = :full_name, avatar_id = :avatar_id, 
                          role = :role, is_active = :is_active";

            $params = [
                ':id' => $id,
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':full_name' => $data['full_name'] ?? null,
                ':avatar_id' => $data['avatar_id'] ?? null,
                ':role' => $data['role'] ?? 'editor',
                ':is_active' => $data['is_active'] ?? 1
            ];

            // Если передан новый пароль, добавляем его в запрос
            if (!empty($data['password'])) {
                $query .= ", password_hash = :password_hash";
                $params[':password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $query .= ", updated_at = NOW() WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            return $stmt->execute($params);
        } catch(PDOException $e) {
            error_log("[USER] Update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить пользователя (с защитой от удаления последнего администратора)
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            // Проверяем, не является ли пользователь последним администратором
            $query = "SELECT COUNT(*) as admin_count FROM " . $this->table . " WHERE role = 'admin'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $user = $this->getById($id);
            
            if ($user && $user['role'] == 'admin' && $result['admin_count'] <= 1) {
                return false; // Нельзя удалить последнего администратора
            }
            
            $query = "DELETE FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("[USER] Delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Обновить время последнего входа
     * @param int $id
     * @return bool
     */
    public function updateLastLogin($id) {
        try {
            $query = "UPDATE " . $this->table . " 
                      SET last_login = NOW() 
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("[USER] Update last login error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Сменить пароль пользователя
     * @param int $id
     * @param string $old_password
     * @param string $new_password
     * @return bool
     */
    public function changePassword($id, $old_password, $new_password) {
        try {
            $query = "SELECT password_hash FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($old_password, $user['password_hash'])) {
                $query = "UPDATE " . $this->table . " 
                          SET password_hash = :password_hash 
                          WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':password_hash', password_hash($new_password, PASSWORD_DEFAULT));
                return $stmt->execute();
            }
        } catch(PDOException $e) {
            error_log("[USER] Change password error: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Проверить, существует ли имя пользователя
     * @param string $username
     * @param int|null $exclude_id ID пользователя, который исключается из проверки (при редактировании)
     * @return bool
     */
    public function isUsernameExists($username, $exclude_id = null) {
        try {
            $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE username = :username";
            $params = [':username' => $username];
            
            if ($exclude_id) {
                $query .= " AND id != :exclude_id";
                $params[':exclude_id'] = $exclude_id;
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        } catch(PDOException $e) {
            error_log("[USER] Check username error: " . $e->getMessage());
            return true; // при ошибке считаем, что имя занято (для безопасности)
        }
    }

    /**
     * Проверить, существует ли email
     * @param string $email
     * @param int|null $exclude_id ID пользователя, который исключается из проверки
     * @return bool
     */
    public function isEmailExists($email, $exclude_id = null) {
        try {
            $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE email = :email";
            $params = [':email' => $email];
            
            if ($exclude_id) {
                $query .= " AND id != :exclude_id";
                $params[':exclude_id'] = $exclude_id;
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        } catch(PDOException $e) {
            error_log("[USER] Check email error: " . $e->getMessage());
            return true;
        }
    }
}
?>