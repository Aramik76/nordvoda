<?php
// models/Project.php
require_once __DIR__ . '/../config/db.php';
require_once 'Gallery.php';

class Project {
    private $conn;
    private $table = 'projects';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Получить последние проекты
    public function getLatest($limit = 6) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE is_published = 1 
                  ORDER BY completion_date DESC, sort_order ASC 
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Получить все проекты
    public function getAllPublished() {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE is_published = 1 
                  ORDER BY completion_date DESC, sort_order ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Получить проект по ID
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Получить обложку проекта
    public function getCover($project_id) {
        $gallery = new Gallery();
        
        $query = "SELECT gallery_id FROM projects_gallery 
                  WHERE project_id = :project_id 
                  ORDER BY sort_order ASC 
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $project_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($result) {
            return $gallery->getById($result['gallery_id']);
        }
        
        return null;
    }

    // Получить все фото проекта
    public function getGallery($project_id) {
        $gallery = new Gallery();
        $photos = [];
        
        $query = "SELECT gallery_id FROM projects_gallery 
                  WHERE project_id = :project_id 
                  ORDER BY sort_order ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $project_id);
        $stmt->execute();
        
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $photo = $gallery->getById($row['gallery_id']);
            if($photo) {
                $photos[] = $photo;
            }
        }
        
        return $photos;
    }

    // Создать проект
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (title, slug, client, location, depth, description, content, cover_image_id, completion_date) 
                  VALUES (:title, :slug, :client, :location, :depth, :description, :content, :cover_image_id, :completion_date)";
        
        $stmt = $this->conn->prepare($query);
        
        if(empty($data['slug'])) {
            $data['slug'] = $this->createSlug($data['title']);
        }
        
        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':slug', $data['slug']);
        $stmt->bindParam(':client', $data['client']);
        $stmt->bindParam(':location', $data['location']);
        $stmt->bindParam(':depth', $data['depth']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':content', $data['content']);
        $stmt->bindParam(':cover_image_id', $data['cover_image_id']);
        $stmt->bindParam(':completion_date', $data['completion_date']);
        
        return $stmt->execute();
    }

    // Добавить фото к проекту
    public function addPhoto($project_id, $gallery_id) {
        $query = "INSERT INTO projects_gallery (project_id, gallery_id) 
                  VALUES (:project_id, :gallery_id)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':project_id', $project_id);
        $stmt->bindParam(':gallery_id', $gallery_id);
        
        return $stmt->execute();
    }

    private function createSlug($string) {
        $string = transliterator_transliterate("Any-Latin; NFD; [:Nonspacing Mark:] Remove; NFC; [:Punctuation:] Remove; Lower();", $string);
        $string = preg_replace('/[-\s]+/', '-', $string);
        return trim($string, '-');
    }
}
?>