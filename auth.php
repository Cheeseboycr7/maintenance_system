<?php
// auth.php
session_start();

class Database {
    private $host = "localhost";
    private $db_name = "maintenance_db";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            throw new Exception("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        if (php_sapi_name() !== 'cli' && !defined('STDIN')) {
            // Check if it's an API request or regular page request
            if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit();
            } else {
                header("Location: login.php");
                exit();
            }
        }
    }
}

function loginUser($username, $password) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user from database
    $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify password (using password_verify for hashed passwords)
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_data'] = $user;
            return true;
        }
        
        // Fallback: check if password matches directly (for demo/admin user)
        if ($password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_data'] = $user;
            return true;
        }
    }
    
    return false;
}

function logoutUser() {
    session_unset();
    session_destroy();
}

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT id, username, created_at FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function isAdmin() {
    $user = getCurrentUser();
    // Since your users table doesn't have a role column, you can implement admin logic based on username
    return $user && $user['username'] === 'admin';
}

// Additional helper functions
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUsername() {
    return $_SESSION['username'] ?? null;
}

// Security: Regenerate session ID after login
function regenerateSession() {
    if (!isset($_SESSION['regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['regenerated'] = true;
    }
}
?>