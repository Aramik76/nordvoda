<?php
// config/db.php
//define('DB_HOST', 'localhost');
//define('DB_PORT', '3306');
//define('DB_NAME', 'aramserg_nordvoda');
//define('DB_USER', 'aramserg_nordvoda');
//define('DB_PASS', 'VV8cvN3e'); 

// Дополнительные настройки
//define('DB_CHARSET', 'utf8mb4');
//define('DB_COLLATE', 'utf8mb4_unicode_ci');



class Database {
    private $host = 'localhost';
    private $dbname = 'aramserg_nordvoda';
    private $username = 'aramserg_nordvoda';
    private $password = 'VV8cvN3e';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            error_log("DB Error: " . $e->getMessage());
            die("Ошибка подключения к базе данных");
        }
        return $this->conn;
    }
}
?>