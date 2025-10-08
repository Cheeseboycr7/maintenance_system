<?php
// test_password.php - Test password hashing and verification
require_once 'config.php';

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test password hashing
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "<h3>Password Hashing Test</h3>";
    echo "Password: " . $password . "<br>";
    echo "Hash: " . $hash . "<br>";
    echo "Verification: " . (password_verify($password, $hash) ? "SUCCESS" : "FAILED") . "<br><br>";
    
    // Check what's in the database
    $stmt = $conn->query("SELECT * FROM users WHERE username = 'admin'");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<h3>Database Contents</h3>";
        echo "Username: " . $user['username'] . "<br>";
        echo "Stored Hash: " . $user['password'] . "<br>";
        echo "Verification with stored hash: " . (password_verify($password, $user['password']) ? "SUCCESS" : "FAILED") . "<br>";
    } else {
        echo "No admin user found in database.<br>";
        echo "Please run the <a href='install.php'>installation script</a> first.";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
    echo "<br>Please run the <a href='install.php'>installation script</a> first.";
}
?>