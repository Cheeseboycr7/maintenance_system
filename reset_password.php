<?php
// reset_password.php - Reset admin password
require_once 'config.php';

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Reset admin password
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = :password WHERE username = 'admin'");
    $stmt->bindParam(':password', $password);
    
    if ($stmt->execute()) {
        echo "Password reset successfully!<br>";
        echo "New password: admin123<br>";
        echo "<a href='login.php'>Go to login</a>";
    } else {
        echo "Error resetting password.";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
    echo "<br>Please run the <a href='install.php'>installation script</a> first.";
}
?>