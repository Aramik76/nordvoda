<?php
// ==============================================
// Gallery.php - Управление медиабиблиотекой
// ==============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Settings.php'; // для получения настроек водяного знака

class Gallery {
    private $conn;
    private $table = 'gallery';
    private $upload_dir;
    private $thumbnail_dir;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        $this->upload_dir = __DIR__ . '/../../public_html/uploads/images/';
        $this->thumbnail_dir = __DIR__ . '/../../public_html/uploads/thumbnails/';
        
        $this->createDirectories();
    }

    /**
     * Создание необходимых директорий, если их нет
     */
    private function createDirectories() {
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0777, true);
        }
        if (!file_exists($this->thumbnail_dir)) {
            mkdir($this->thumbnail_dir, 0777, true);
        }
    }

    /**
     * Получить все изображения
     * @param bool $published_only только опубликованные
     * @return array
     */
    public function getAll($published_only = false) {
        try {
            $query = "SELECT * FROM " . $this->table;
            if($published_only) {
                $query .= " WHERE is_published = 1";
            }
            $query .= " ORDER BY sort_order ASC, created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("[GALLERY] Get all error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Получить изображение по ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("[GALLERY] Get by id error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Загрузка нового файла в галерею
     * @param array $file $_FILES['file']
     * @param string $alt_text
     * @param string $title
     * @return array|mixed массив с данными нового файла или ['error' => ...]
     */
    public function upload($file, $alt_text = '', $title = '') {
        if($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Ошибка загрузки файла: код ' . $file['error']];
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if(!in_array($mime_type, $allowed_types)) {
            return ['error' => 'Неподдерживаемый формат файла. Разрешены: JPG, PNG, GIF, WEBP'];
        }

        if($file['size'] > 10 * 1024 * 1024) {
            return ['error' => 'Файл слишком большой. Максимальный размер: 10MB'];
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $this->upload_dir . $filename;
        
        list($width, $height) = getimagesize($file['tmp_name']);

        if(move_uploaded_file($file['tmp_name'], $filepath)) {
            $thumbnail = $this->createThumbnail($filepath, $filename);
            
            $web_path = '/uploads/images/' . $filename;
            $thumb_path = $thumbnail ? '/uploads/thumbnails/thumb_' . $filename : null;
            
            try {
                $query = "INSERT INTO " . $this->table . " 
                          (filename, original_name, filepath, thumbnail, alt_text, title, filesize, mime_type, width, height) 
                          VALUES (:filename, :original_name, :filepath, :thumbnail, :alt_text, :title, :filesize, :mime_type, :width, :height)";
                
                $stmt = $this->conn->prepare($query);
                $stmt->execute([
                    ':filename' => $filename,
                    ':original_name' => $file['name'],
                    ':filepath' => $web_path,
                    ':thumbnail' => $thumb_path,
                    ':alt_text' => $alt_text,
                    ':title' => $title,
                    ':filesize' => $file['size'],
                    ':mime_type' => $mime_type,
                    ':width' => $width,
                    ':height' => $height
                ]);
                
                $id = $this->conn->lastInsertId();
                return $this->getById($id);
                
            } catch(PDOException $e) {
                unlink($filepath);
                if($thumbnail) unlink($this->thumbnail_dir . 'thumb_' . $filename);
                error_log("[GALLERY] Upload DB error: " . $e->getMessage());
                return ['error' => 'Ошибка сохранения в базу данных'];
            }
        }

        return ['error' => 'Не удалось сохранить файл'];
    }

    /**
     * Создание миниатюры изображения
     * @param string $filepath полный путь к оригиналу
     * @param string $filename имя файла
     * @param int $max_width
     * @param int $max_height
     * @return string|null путь к миниатюре (относительно public_html) или null
     */
    public function createThumbnail($filepath, $filename, $max_width = 300, $max_height = 300) {
        $thumbnail_path = $this->thumbnail_dir . 'thumb_' . $filename;
        
        list($width, $height) = getimagesize($filepath);
        
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);
        
        $thumb = imagecreatetruecolor($new_width, $new_height);
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        switch($extension) {
            case 'jpeg':
            case 'jpg':
                $source = imagecreatefromjpeg($filepath);
                imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                imagejpeg($thumb, $thumbnail_path, 85);
                break;
            case 'png':
                $source = imagecreatefrompng($filepath);
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                imagepng($thumb, $thumbnail_path, 8);
                break;
            case 'gif':
                $source = imagecreatefromgif($filepath);
                imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                imagegif($thumb, $thumbnail_path);
                break;
            case 'webp':
                if(function_exists('imagecreatefromwebp')) {
                    $source = imagecreatefromwebp($filepath);
                    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                    imagewebp($thumb, $thumbnail_path, 85);
                } else {
                    imagedestroy($thumb);
                    return null;
                }
                break;
            default:
                imagedestroy($thumb);
                return null;
        }
        
        imagedestroy($source);
        imagedestroy($thumb);
        
        return '/uploads/thumbnails/thumb_' . $filename;
    }

    /**
     * Удаление изображения и связанных файлов
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        try {
            $image = $this->getById($id);
            
            if($image) {
                $full_path = __DIR__ . '/../../public_html' . $image['filepath'];
                if(file_exists($full_path)) {
                    unlink($full_path);
                }
                
                if($image['thumbnail']) {
                    $thumb_path = __DIR__ . '/../../public_html' . $image['thumbnail'];
                    if(file_exists($thumb_path)) {
                        unlink($thumb_path);
                    }
                }
                
                $query = "DELETE FROM " . $this->table . " WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $id);
                return $stmt->execute();
            }
        } catch(PDOException $e) {
            error_log("[GALLERY] Delete error: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Обновление метаданных изображения
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        try {
            $query = "UPDATE " . $this->table . " 
                      SET alt_text = :alt_text, title = :title, 
                          description = :description, sort_order = :sort_order,
                          is_published = :is_published, updated_at = NOW()
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                ':id' => $id,
                ':alt_text' => $data['alt_text'] ?? null,
                ':title' => $data['title'] ?? null,
                ':description' => $data['description'] ?? null,
                ':sort_order' => $data['sort_order'] ?? 0,
                ':is_published' => $data['is_published'] ?? 1
            ]);
        } catch(PDOException $e) {
            error_log("[GALLERY] Update error: " . $e->getMessage());
            return false;
        }
    }

    // ==================== МЕТОДЫ ДЛЯ ВОДЯНОГО ЗНАКА ====================

    /**
     * Наложить водяной знак на изображение (создаёт копию)
     * @param int $id ID изображения
     * @param array $options параметры наложения (position, padding, opacity, scale, action)
     * @return bool|int ID новой записи или false при ошибке
     */
    public function applyWatermark($id, $options = []) {
        $image = $this->getById($id);
        if (!$image) {
            error_log("[WATERMARK] Изображение с ID $id не найдено в БД");
            return false;
        }

        // Проверка, не наложен ли уже водяной знак (по полю watermarked)
        if (isset($image['watermarked']) && $image['watermarked'] == 1) {
            error_log("[WATERMARK] Изображение ID $id уже имеет водяной знак. Повторное наложение запрещено.");
            return false;
        }

        $settings = new Settings();
        $settings->refresh(); // Принудительно обновляем кэш, чтобы гарантировать свежие настройки

        $position = $options['position'] ?? $settings->get('watermark_position', 'bottom_right');
        $padding = intval($options['padding'] ?? $settings->get('watermark_padding', 10));
        $opacity = intval($options['opacity'] ?? $settings->get('watermark_opacity', 50));
        $scale = isset($options['scale']) ? $options['scale'] : $settings->get('watermark_scale', '0');
        $action = $options['action'] ?? $settings->get('watermark_action', 'copy'); // 'copy' или 'replace'
        $logo_path = $settings->get('site_logo', '/assets/img/logo.png');

        // Логируем полученные настройки для отладки
        error_log("[WATERMARK] ID=$id: position=$position, padding=$padding, opacity=$opacity, scale=$scale, action=$action, logo=$logo_path");

        $original_path = __DIR__ . '/../../public_html' . $image['filepath'];
        if (!file_exists($original_path)) {
            error_log("[WATERMARK] Файл не существует: $original_path");
            return false;
        }

        $logo_full_path = __DIR__ . '/../../public_html' . $logo_path;
        if (!file_exists($logo_full_path)) {
            error_log("[WATERMARK] Логотип не найден: $logo_full_path");
            return false;
        }

        // Создаём ресурсы
        $original = $this->imageCreateFromAny($original_path);
        if (!$original) {
            error_log("[WATERMARK] Не удалось создать ресурс из оригинального изображения: $original_path");
            return false;
        }

        $logo = $this->imageCreateFromAny($logo_full_path);
        if (!$logo) {
            error_log("[WATERMARK] Не удалось создать ресурс из логотипа: $logo_full_path");
            imagedestroy($original);
            return false;
        }

        $orig_w = imagesx($original);
        $orig_h = imagesy($original);
        $logo_w = imagesx($logo);
        $logo_h = imagesy($logo);

        // Масштабирование логотипа, если включено
        if ($scale == '1') {
            $logo_w = $orig_w;
            $logo_h = $orig_h;
            // Создаём масштабированный логотип
            $scaled_logo = imagecreatetruecolor($logo_w, $logo_h);
            imagealphablending($scaled_logo, false);
            imagesavealpha($scaled_logo, true);
            imagecopyresampled($scaled_logo, $logo, 0, 0, 0, 0, $logo_w, $logo_h, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
            $logo = $scaled_logo;
        } else {
            // Если логотип больше изображения – уменьшаем его пропорционально
            if ($logo_w > $orig_w || $logo_h > $orig_h) {
                $ratio = min($orig_w / $logo_w, $orig_h / $logo_h);
                $new_w = round($logo_w * $ratio);
                $new_h = round($logo_h * $ratio);
                $scaled_logo = imagecreatetruecolor($new_w, $new_h);
                imagealphablending($scaled_logo, false);
                imagesavealpha($scaled_logo, true);
                imagecopyresampled($scaled_logo, $logo, 0, 0, 0, 0, $new_w, $new_h, $logo_w, $logo_h);
                imagedestroy($logo);
                $logo = $scaled_logo;
                $logo_w = $new_w;
                $logo_h = $new_h;
            }
        }

        // Вычисляем координаты
        switch ($position) {
            case 'top_left':
                $dest_x = $padding;
                $dest_y = $padding;
                break;
            case 'top_right':
                $dest_x = $orig_w - $logo_w - $padding;
                $dest_y = $padding;
                break;
            case 'bottom_left':
                $dest_x = $padding;
                $dest_y = $orig_h - $logo_h - $padding;
                break;
            case 'center':
                $dest_x = ($orig_w - $logo_w) / 2;
                $dest_y = ($orig_h - $logo_h) / 2;
                break;
            case 'bottom_right':
            default:
                $dest_x = $orig_w - $logo_w - $padding;
                $dest_y = $orig_h - $logo_h - $padding;
                break;
        }

        $dest_x = (int)$dest_x;
        $dest_y = (int)$dest_y;

        // Создаём копию оригинального изображения для наложения
        $output = imagecreatetruecolor($orig_w, $orig_h);
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagecopy($output, $original, 0, 0, 0, 0, $orig_w, $orig_h);

        // Наложение водяного знака
        $this->imageCopyMergeAlpha($output, $logo, $dest_x, $dest_y, 0, 0, $logo_w, $logo_h, $opacity);

        // Определяем путь для сохранения
        $pathinfo = pathinfo($original_path);
        $ext = $pathinfo['extension'];
        $filename = $pathinfo['filename'];
        $dir = $pathinfo['dirname'];

        if ($action === 'copy') {
            // Получаем оригинальное имя файла (без пути) из поля original_name
            $orig_full = $image['original_name'];
            $orig_filename = pathinfo($orig_full, PATHINFO_FILENAME);
            // Очищаем от недопустимых символов (оставляем буквы, цифры, дефис, подчёркивание, точку)
            $safe_filename = preg_replace('/[^a-zA-Zа-яА-Я0-9\-_\.]/u', '_', $orig_filename);
            if (empty($safe_filename)) {
                $safe_filename = 'image';
            }
            // Формируем новое имя: оригинальное_watermarked_уникальный_суффикс.расширение
            $new_filename = $safe_filename . '_watermarked_' . uniqid() . '.' . $ext;
            $new_web_path = '/uploads/images/' . $new_filename;
            $new_full_path = $dir . '/' . $new_filename;
        } else {
            // replace – перезаписываем оригинал (не рекомендуется, но оставлено для обратной совместимости)
            $new_full_path = $original_path;
            $new_web_path = $image['filepath'];
        }

        // Сохраняем
        $saved = $this->saveImage($output, $new_full_path, $ext);

        imagedestroy($original);
        imagedestroy($logo);
        imagedestroy($output);

        if (!$saved) {
            error_log("[WATERMARK] Не удалось сохранить изображение: $new_full_path");
            return false;
        }

        // Если создана копия, добавляем запись в БД
        if ($action === 'copy') {
            // Получаем размеры нового файла
            list($width, $height) = getimagesize($new_full_path);
            $filesize = filesize($new_full_path);
            $mime_type = image_type_to_mime_type(exif_imagetype($new_full_path));

            $thumbnail = $this->createThumbnail($new_full_path, $new_filename);

            $query = "INSERT INTO " . $this->table . " 
                      (filename, original_name, filepath, thumbnail, alt_text, title, filesize, mime_type, width, height, watermarked) 
                      VALUES (:filename, :original_name, :filepath, :thumbnail, :alt_text, :title, :filesize, :mime_type, :width, :height, 1)";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':filename' => $new_filename,
                ':original_name' => 'watermarked_' . $image['original_name'],
                ':filepath' => $new_web_path,
                ':thumbnail' => $thumbnail,
                ':alt_text' => $image['alt_text'] . ' (с водяным знаком)',
                ':title' => $image['title'] . ' (с водяным знаком)',
                ':filesize' => $filesize,
                ':mime_type' => $mime_type,
                ':width' => $width,
                ':height' => $height
            ]);

            $new_id = $this->conn->lastInsertId();
            return $new_id;
        }

        // Если замена – обновляем поле watermarked у оригинала
        $updateQuery = "UPDATE " . $this->table . " SET watermarked = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->execute([':id' => $id]);
        $this->createThumbnail($original_path, $pathinfo['basename']);
        return true;
    }

    /**
     * Создать ресурс изображения из файла любого поддерживаемого типа
     * @param string $path
     * @return resource|false
     */
    private function imageCreateFromAny($path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'jpeg':
            case 'jpg':
                return imagecreatefromjpeg($path);
            case 'png':
                return imagecreatefrompng($path);
            case 'gif':
                return imagecreatefromgif($path);
            case 'webp':
                if (function_exists('imagecreatefromwebp')) {
                    return imagecreatefromwebp($path);
                }
                error_log("[WATERMARK] Функция imagecreatefromwebp не поддерживается");
                return false;
            default:
                error_log("[WATERMARK] Неподдерживаемый тип файла: $ext");
                return false;
        }
    }

    /**
     * Копирование и слияние изображений с поддержкой прозрачности
     * @param resource $dst_im
     * @param resource $src_im
     */
    private function imageCopyMergeAlpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct) {
        $cut = imagecreatetruecolor($src_w, $src_h);
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
        imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
        imagedestroy($cut);
    }

    /**
     * Сохранить ресурс изображения в файл
     * @param resource $image
     * @param string $path
     * @param string $ext
     * @return bool
     */
    private function saveImage($image, $path, $ext) {
        switch (strtolower($ext)) {
            case 'jpeg':
            case 'jpg':
                return imagejpeg($image, $path, 90);
            case 'png':
                return imagepng($image, $path, 9);
            case 'gif':
                return imagegif($image, $path);
            case 'webp':
                return imagewebp($image, $path, 90);
            default:
                return false;
        }
    }
}
?>