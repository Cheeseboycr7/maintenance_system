<?php
// install.php
header('Content-Type: text/html; charset=utf-8');

// Database configuration
$host = 'localhost';
$dbname = 'maintenance_db';
$username = 'root';
$password = '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            role ENUM('admin', 'user') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            phone VARCHAR(20),
            position VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            assigned_to INT,
            priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
            status ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
            progress INT DEFAULT 0 CHECK (progress >= 0 AND progress <= 100),
            due_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE SET NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            location VARCHAR(255) NOT NULL,
            activity_date DATE NOT NULL,
            assigned_to INT,
            priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
            status ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE SET NULL
        )"
    ];
    
    foreach ($tables as $table) {
        $db->exec($table);
    }
    
    // Insert default admin user
    $checkUser = $db->query("SELECT id FROM users WHERE username = 'admin'");
    if ($checkUser->rowCount() === 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, name, email, role) VALUES ('admin', ?, 'Administrator', 'admin@example.com', 'admin')");
        $stmt->execute([$hashedPassword]);
    }
    
    // Insert sample data
    $checkEmployees = $db->query("SELECT id FROM employees LIMIT 1");
    if ($checkEmployees->rowCount() === 0) {
        $sampleEmployees = [
            ['John Smith', 'john.smith@example.com', '555-0101', 'Maintenance Technician'],
            ['Maria Garcia', 'maria.garcia@example.com', '555-0102', 'HVAC Specialist']
        ];
        
        foreach ($sampleEmployees as $employee) {
            $stmt = $db->prepare("INSERT INTO employees (name, email, phone, position) VALUES (?, ?, ?, ?)");
            $stmt->execute($employee);
        }
    }
    
    echo "<h1>Installation Successful</h1>";
    echo "<p>Database tables created successfully with sample data.</p>";
    echo "<h2>Login Credentials:</h2>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<h1>Installation Failed</h1>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database configuration.</p>";
}
?>