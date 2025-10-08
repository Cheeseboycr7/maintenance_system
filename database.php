<?php
// Check if config constants are already defined
if (!defined('DB_HOST')) {
    require_once 'config.php';
}

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if tables exist, create if they don't
            $this->createTables();
        } catch(PDOException $exception) {
            // If database doesn't exist, try to create it
            if ($exception->getCode() == 1049) {
                try {
                    $this->conn = new PDO("mysql:host=" . $this->host, $this->username, $this->password);
                    $this->conn->exec("CREATE DATABASE IF NOT EXISTS `$this->db_name`");
                    $this->conn->exec("USE `$this->db_name`");
                    $this->createTables();
                } catch(PDOException $e) {
                    echo "Database creation error: " . $e->getMessage();
                }
            } else {
                echo "Connection error: " . $exception->getMessage();
            }
        }

        return $this->conn;
    }

    private function createTables() {
        // Users table for authentication
        $this->conn->exec("CREATE TABLE IF NOT EXISTS users (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Employees table
        $this->conn->exec("CREATE TABLE IF NOT EXISTS employees (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            phone VARCHAR(20),
            position VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Tasks table
        $this->conn->exec("CREATE TABLE IF NOT EXISTS tasks (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            assigned_to INT(11),
            priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
            status ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
            progress INT(3) DEFAULT 0,
            due_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE SET NULL
        )");

        // Activities table
        $this->conn->exec("CREATE TABLE IF NOT EXISTS activities (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            location VARCHAR(255) NOT NULL,
            activity_date DATE NOT NULL,
            assigned_to INT(11),
            priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
            status ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE SET NULL
        )");

        // Insert default admin user if not exists
        $checkUser = $this->conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($checkUser == 0) {
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("INSERT INTO users (username, password) VALUES ('admin', :password)");
            $stmt->bindParam(':password', $password);
            $stmt->execute();
        }
    }
}
?>