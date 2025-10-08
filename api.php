<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "maintenance_db";

// Create database connection
$connect = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$connect) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . mysqli_connect_error()]);
    exit;
}

// Set charset to UTF-8
mysqli_set_charset($connect, "utf8mb4");

// Get the HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Get query parameters
$table = $_GET['table'] ?? '';
$id = $_GET['id'] ?? '';

// If no table parameter, try to get from path info
if (empty($table)) {
    $request_uri = $_SERVER['REQUEST_URI'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    
    // Extract path after the script name
    $path = str_replace($script_name, '', $request_uri);
    $path_segments = explode('/', trim($path, '/'));
    
    if (count($path_segments) > 0) {
        $table = $path_segments[0];
        if (count($path_segments) > 1) {
            $id = $path_segments[1];
        }
    }
}

// API Router
switch ($method) {
    case 'GET':
        handleGetRequest($connect, $table, $id);
        break;
    case 'POST':
        handlePostRequest($connect, $table);
        break;
    case 'PUT':
        handlePutRequest($connect, $table, $id);
        break;
    case 'DELETE':
        handleDeleteRequest($connect, $table, $id);
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}

// Close database connection
mysqli_close($connect);

// Handle GET requests
function handleGetRequest($connect, $table, $id) {
    $allowedTables = ['users', 'employees', 'tasks', 'activities'];
    
    if (empty($table) || !in_array($table, $allowedTables)) {
        http_response_code(400);
        echo json_encode([
            "error" => "Invalid or missing table name", 
            "available_tables" => $allowedTables,
            "usage" => "Use ?table=employees or /api.php/employees"
        ]);
        return;
    }
    
    // Build WHERE clause for specific tables
    $where_clause = "";
    if ($table === 'tasks' && empty($id)) {
        // Optional: Add filters for tasks
        $status = $_GET['status'] ?? '';
        $priority = $_GET['priority'] ?? '';
        
        $conditions = [];
        if (!empty($status)) {
            $conditions[] = "status = '" . mysqli_real_escape_string($connect, $status) . "'";
        }
        if (!empty($priority)) {
            $conditions[] = "priority = '" . mysqli_real_escape_string($connect, $priority) . "'";
        }
        
        if (!empty($conditions)) {
            $where_clause = "WHERE " . implode(' AND ', $conditions);
        }
    }
    
    if (!empty($id) && is_numeric($id)) {
        // Get single record
        $id = mysqli_real_escape_string($connect, $id);
        $sql = "SELECT * FROM $table WHERE id = $id";
    } else {
        // Get all records
        $sql = "SELECT * FROM $table $where_clause ORDER BY id DESC";
    }
    
    $result = mysqli_query($connect, $sql);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(["error" => "Query failed: " . mysqli_error($connect)]);
        return;
    }
    
    $json_array = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $json_array[] = $row;
    }
    
    // If single record requested but not found
    if (!empty($id) && empty($json_array)) {
        http_response_code(404);
        echo json_encode(["error" => "Record not found"]);
        return;
    }
    
    echo json_encode([
        "success" => true,
        "data" => $json_array,
        "count" => count($json_array)
    ], JSON_PRETTY_PRINT);
}

// Handle POST requests
function handlePostRequest($connect, $table) {
    $allowedTables = ['users', 'employees', 'tasks', 'activities'];
    
    if (empty($table) || !in_array($table, $allowedTables)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid or missing table name"]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        // Try form data if JSON is empty
        $input = $_POST;
    }
    
    if (empty($input)) {
        http_response_code(400);
        echo json_encode(["error" => "No data provided"]);
        return;
    }
    
    $columns = [];
    $values = [];
    
    foreach ($input as $key => $value) {
        $columns[] = mysqli_real_escape_string($connect, $key);
        $values[] = "'" . mysqli_real_escape_string($connect, $value) . "'";
    }
    
    $columns_str = implode(', ', $columns);
    $values_str = implode(', ', $values);
    
    $sql = "INSERT INTO $table ($columns_str) VALUES ($values_str)";
    $result = mysqli_query($connect, $sql);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(["error" => "Insert failed: " . mysqli_error($connect)]);
        return;
    }
    
    $new_id = mysqli_insert_id($connect);
    echo json_encode([
        "success" => true,
        "message" => "Record created successfully", 
        "id" => $new_id
    ]);
}

// Handle PUT requests
function handlePutRequest($connect, $table, $id) {
    $allowedTables = ['users', 'employees', 'tasks', 'activities'];
    
    if (empty($table) || !in_array($table, $allowedTables) || empty($id)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid table name or missing ID"]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input)) {
        http_response_code(400);
        echo json_encode(["error" => "No data provided"]);
        return;
    }
    
    $updates = [];
    foreach ($input as $key => $value) {
        $updates[] = mysqli_real_escape_string($connect, $key) . " = '" . mysqli_real_escape_string($connect, $value) . "'";
    }
    
    $updates_str = implode(', ', $updates);
    $id = mysqli_real_escape_string($connect, $id);
    
    $sql = "UPDATE $table SET $updates_str WHERE id = $id";
    $result = mysqli_query($connect, $sql);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(["error" => "Update failed: " . mysqli_error($connect)]);
        return;
    }
    
    echo json_encode([
        "success" => true,
        "message" => "Record updated successfully",
        "affected_rows" => mysqli_affected_rows($connect)
    ]);
}

// Handle DELETE requests
function handleDeleteRequest($connect, $table, $id) {
    $allowedTables = ['users', 'employees', 'tasks', 'activities'];
    
    if (empty($table) || !in_array($table, $allowedTables) || empty($id)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid table name or missing ID"]);
        return;
    }
    
    $id = mysqli_real_escape_string($connect, $id);
    $sql = "DELETE FROM $table WHERE id = $id";
    $result = mysqli_query($connect, $sql);
    
    if (!$result) {
        http_response_code(500);
        echo json_encode(["error" => "Delete failed: " . mysqli_error($connect)]);
        return;
    }
    
    $affected_rows = mysqli_affected_rows($connect);
    if ($affected_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "Record not found"]);
        return;
    }
    
    echo json_encode([
        "success" => true,
        "message" => "Record deleted successfully",
        "affected_rows" => $affected_rows
    ]);
}
?>