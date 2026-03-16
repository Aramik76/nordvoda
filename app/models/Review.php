<?php
// ==============================================
// Review.php - Управление отзывами
// ==============================================
require_once __DIR__ . '/../config/db.php';

class Review {
    private $conn;
    private $table = 'reviews';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        $this->createTable();
    }

    private function createTable() {
        $query = "CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            author VARCHAR(255) NOT NULL,
            avatar_id INT NULL,
            rating INT DEFAULT 5,
            text TEXT NOT NULL,
            project_id INT NULL,
            project_title VARCHAR(255) NULL,
            is_published TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (avatar_id) REFERENCES gallery(id) ON DELETE SET NULL,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
            INDEX idx_published (is_published)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        try {
            $this->conn->exec($query);
        } catch(PDOException $e) {
            error_log("[REVIEW] Create table error: " . $e->getMessage());
        }
    }

    public function getAllPublished() {
        try {
            $query = "SELECT r.*, p.title as project_title 
                      FROM " . $this->table . " r
                      LEFT JOIN projects p ON r.project_id = p.id
                      WHERE r.is_published = 1 
                      ORDER BY r.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("[REVIEW] Get all error: " . $e->getMessage());
            return [];
        }
    }

    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("[REVIEW] Get by id error: " . $e->getMessage());
            return null;
        }
    }

    public function create($data) {
        try {
            $query = "INSERT INTO " . $this->table . " 
                      (author, avatar_id, rating, text, project_id, project_title, is_published) 
                      VALUES (:author, :avatar_id, :rating, :text, :project_id, :project_title, :is_published)";
            
            $stmt = $this->conn->prepare($query);
            
            return $stmt->execute([
                ':author' => $data['author'],
                ':avatar_id' => $data['avatar_id'] ?? null,
                ':rating' => $data['rating'] ?? 5,
                ':text' => $data['text'],
                ':project_id' => $data['project_id'] ?? null,
                ':project_title' => $data['project_title'] ?? null,
                ':is_published' => $data['is_published'] ?? 1
            ]);
        } catch(PDOException $e) {
            error_log("[REVIEW] Create error: " . $e->getMessage());
            return false;
        }
    }

    public function update($id, $data) {
        try {
            $query = "UPDATE " . $this->table . " 
                      SET author = :author, avatar_id = :avatar_id, 
                          rating = :rating, text = :text, 
                          project_id = :project_id, project_title = :project_title, 
                          is_published = :is_published, updated_at = NOW()
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            
            return $stmt->execute([
                ':id' => $id,
                ':author' => $data['author'],
                ':avatar_id' => $data['avatar_id'] ?? null,
                ':rating' => $data['rating'] ?? 5,
                ':text' => $data['text'],
                ':project_id' => $data['project_id'] ?? null,
                ':project_title' => $data['project_title'] ?? null,
                ':is_published' => $data['is_published'] ?? 1
            ]);
        } catch(PDOException $e) {
            error_log("[REVIEW] Update error: " . $e->getMessage());
            return false;
        }
    }

    public function delete($id) {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("[REVIEW] Delete error: " . $e->getMessage());
            return false;
        }
    }

    public function getAverageRating() {
        try {
            $query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total 
                      FROM " . $this->table . " 
                      WHERE is_published = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("[REVIEW] Get average error: " . $e->getMessage());
            return ['avg_rating' => 0, 'total' => 0];
        }
    }
}
?>