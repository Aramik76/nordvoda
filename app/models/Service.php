<?php
// ==============================================
// Service.php - Управление услугами
// ==============================================
require_once __DIR__ . '/../config/db.php';

class Service {
    private $conn;
    private $table = 'services';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAllPublished() {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                      WHERE is_published = 1 
                      ORDER BY sort_order ASC, id DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("[SERVICE] Get all error: " . $e->getMessage());
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
            error_log("[SERVICE] Get by id error: " . $e->getMessage());
            return null;
        }
    }

    public function getBySlug($slug) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE slug = :slug";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':slug', $slug);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("[SERVICE] Get by slug error: " . $e->getMessage());
            return null;
        }
    }

    public function create($data) {
        try {
            $query = "INSERT INTO " . $this->table . " 
                      (title, slug, description, full_description, icon, image_id, price, features, sort_order) 
                      VALUES (:title, :slug, :description, :full_description, :icon, :image_id, :price, :features, :sort_order)";
            
            $stmt = $this->conn->prepare($query);
            
            if(empty($data['slug'])) {
                $data['slug'] = $this->createSlug($data['title']);
            }
            
            return $stmt->execute([
                ':title' => $data['title'],
                ':slug' => $data['slug'],
                ':description' => $data['description'] ?? null,
                ':full_description' => $data['full_description'] ?? null,
                ':icon' => $data['icon'] ?? null,
                ':image_id' => $data['image_id'] ?? null,
                ':price' => $data['price'] ?? null,
                ':features' => $data['features'] ?? null,
                ':sort_order' => $data['sort_order'] ?? 0
            ]);
        } catch(PDOException $e) {
            error_log("[SERVICE] Create error: " . $e->getMessage());
            return false;
        }
    }

    public function update($id, $data) {
        try {
            $query = "UPDATE " . $this->table . " 
                      SET title = :title, slug = :slug, description = :description, 
                          full_description = :full_description, icon = :icon, 
                          image_id = :image_id, price = :price, features = :features, 
                          sort_order = :sort_order, is_published = :is_published,
                          updated_at = NOW()
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            
            return $stmt->execute([
                ':id' => $id,
                ':title' => $data['title'],
                ':slug' => $data['slug'] ?? $this->createSlug($data['title']),
                ':description' => $data['description'] ?? null,
                ':full_description' => $data['full_description'] ?? null,
                ':icon' => $data['icon'] ?? null,
                ':image_id' => $data['image_id'] ?? null,
                ':price' => $data['price'] ?? null,
                ':features' => $data['features'] ?? null,
                ':sort_order' => $data['sort_order'] ?? 0,
                ':is_published' => $data['is_published'] ?? 1
            ]);
        } catch(PDOException $e) {
            error_log("[SERVICE] Update error: " . $e->getMessage());
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
            error_log("[SERVICE] Delete error: " . $e->getMessage());
            return false;
        }
    }

    private function createSlug($string) {
        $string = mb_strtolower($string, 'UTF-8');
        $string = str_replace([
            'а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п',
            'р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я'
        ], [
            'a','b','v','g','d','e','e','zh','z','i','y','k','l','m','n','o','p',
            'r','s','t','u','f','h','ts','ch','sh','sch','','y','','e','yu','ya'
        ], $string);
        $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
        $string = preg_replace('/[\s-]+/', '-', $string);
        $string = trim($string, '-');
        return $string;
    }
}
?>