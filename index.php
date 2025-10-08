<?php
// api/index.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, Accept, X-Requested-With');
header('Content-Type: application/json');

require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the request URI and extract the endpoint
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME']; // /maintenance_system/api/index.php

// Remove the script path from request URI to get the endpoint
$path = str_replace(dirname($script_name), '', $request_uri);
$path = trim($path, '/');
$path_segments = explode('/', $path);

// The first segment is the endpoint
$endpoint = $path_segments[0] ?? '';

// Remove query string from endpoint
$endpoint = strtok($endpoint, '?');

// Debug information (you can remove this later)
error_log("Request URI: " . $request_uri);
error_log("Script Name: " . $script_name);
error_log("Path: " . $path);
error_log("Endpoint: " . $endpoint);

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = [];
}

// Get query parameters
$query_params = $_GET;

// Route the request
try {
    // Check authentication for all routes except login
    if ($endpoint !== 'login') {
        redirectIfNotLoggedIn();
    }

    $database = new Database();
    $db = $database->getConnection();

    switch ($endpoint) {
        case 'login':
            handleLogin($db, $input);
            break;
        case 'dashboard':
            handleDashboard($db, $query_params);
            break;
        case 'employees':
            handleEmployees($db, $method, $path_segments, $input, $query_params);
            break;
        case 'tasks':
            handleTasks($db, $method, $path_segments, $input, $query_params);
            break;
        case 'activities':
            handleActivities($db, $method, $path_segments, $input, $query_params);
            break;
        case 'reports':
            handleReports($db, $method, $input, $query_params);
            break;
        case '':
            // Root endpoint - show available endpoints
            echo json_encode([
                'message' => 'Maintenance System API',
                'endpoints' => [
                    'POST /login' => 'User authentication',
                    'GET /dashboard' => 'Dashboard statistics',
                    'GET /employees' => 'List employees',
                    'GET /tasks' => 'List tasks',
                    'GET /activities' => 'List activities',
                    'POST /reports' => 'Generate reports'
                ],
                'usage' => 'Send POST request to /login first to authenticate'
            ]);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found: ' . $endpoint]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// ... rest of your handler functions remain the same ...

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/api/';

// Extract the endpoint from the URL
$path = str_replace($base_path, '', $request_uri);
$path_segments = explode('/', $path);
$endpoint = $path_segments[0];

// Remove query string from endpoint
$endpoint = strtok($endpoint, '?');

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = [];
}

// Get query parameters
$query_params = $_GET;

// Route the request
try {
    // Check authentication for all routes except login
    if ($endpoint !== 'login') {
        redirectIfNotLoggedIn();
    }

    $database = new Database();
    $db = $database->getConnection();

    switch ($endpoint) {
        case 'login':
            handleLogin($db, $input);
            break;
        case 'logout':
            handleLogout();
            break;
        case 'dashboard':
            handleDashboard($db, $query_params);
            break;
        case 'employees':
            handleEmployees($db, $method, $path_segments, $input, $query_params);
            break;
        case 'tasks':
            handleTasks($db, $method, $path_segments, $input, $query_params);
            break;
        case 'activities':
            handleActivities($db, $method, $path_segments, $input, $query_params);
            break;
        case 'reports':
            handleReports($db, $method, $input, $query_params);
            break;
        case 'users':
            handleUsers($db, $method, $path_segments, $input, $query_params);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// API endpoint handlers
function handleLogin($db, $input) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password are required']);
        return;
    }

    // For demo purposes - in production, use proper password hashing
    $stmt = $db->prepare("SELECT id, username, name, email FROM users WHERE username = :username AND password = :password");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $password);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_data'] = $user;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Login successful',
            'user' => $user
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password']);
    }
}

function handleLogout() {
    session_unset();
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logout successful']);
}

function handleDashboard($db, $query_params) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // Get dashboard statistics
    $stats = [];
    
    // Total tasks
    $stmt = $db->query("SELECT COUNT(*) as total_tasks FROM tasks");
    $stats['total_tasks'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_tasks'];
    
    // Completed tasks
    $stmt = $db->query("SELECT COUNT(*) as completed_tasks FROM tasks WHERE status = 'Completed'");
    $stats['completed_tasks'] = $stmt->fetch(PDO::FETCH_ASSOC)['completed_tasks'];
    
    // Pending tasks
    $stmt = $db->query("SELECT COUNT(*) as pending_tasks FROM tasks WHERE status = 'Pending'");
    $stats['pending_tasks'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_tasks'];
    
    // In progress tasks
    $stmt = $db->query("SELECT COUNT(*) as in_progress_tasks FROM tasks WHERE status = 'In Progress'");
    $stats['in_progress_tasks'] = $stmt->fetch(PDO::FETCH_ASSOC)['in_progress_tasks'];
    
    // Total employees
    $stmt = $db->query("SELECT COUNT(*) as total_employees FROM employees");
    $stats['total_employees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_employees'];
    
    // Total activities
    $stmt = $db->query("SELECT COUNT(*) as total_activities FROM activities");
    $stats['total_activities'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_activities'];
    
    // Recent activities (last 5)
    $stmt = $db->query("
        SELECT a.*, e.name as assigned_name 
        FROM activities a 
        LEFT JOIN employees e ON a.assigned_to = e.id 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");
    $stats['recent_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent tasks (last 5)
    $stmt = $db->query("
        SELECT t.*, e.name as assigned_name 
        FROM tasks t 
        LEFT JOIN employees e ON t.assigned_to = e.id 
        ORDER BY t.created_at DESC 
        LIMIT 5
    ");
    $stats['recent_tasks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tasks by status for chart
    $stmt = $db->query("
        SELECT status, COUNT(*) as count 
        FROM tasks 
        GROUP BY status
    ");
    $stats['tasks_by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Activities by status for chart
    $stmt = $db->query("
        SELECT status, COUNT(*) as count 
        FROM activities 
        GROUP BY status
    ");
    $stats['activities_by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($stats);
}

function handleEmployees($db, $method, $path_segments, $input, $query_params) {
    $id = isset($path_segments[1]) && is_numeric($path_segments[1]) ? intval($path_segments[1]) : null;

    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single employee
                $stmt = $db->prepare("SELECT * FROM employees WHERE id = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($employee) {
                    echo json_encode($employee);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Employee not found']);
                }
            } else {
                // Get all employees with filters
                $search = $query_params['search'] ?? '';
                $sort_by = $query_params['sort_by'] ?? 'name';
                $sort_order = $query_params['sort_order'] ?? 'ASC';
                
                $query = "SELECT * FROM employees WHERE 1=1";
                $params = [];
                
                if (!empty($search)) {
                    $query .= " AND (name LIKE :search OR email LIKE :search OR position LIKE :search OR phone LIKE :search)";
                    $params[':search'] = "%$search%";
                }
                
                // Add sorting
                $valid_sort_columns = ['id', 'name', 'email', 'phone', 'position'];
                $sort_by = in_array($sort_by, $valid_sort_columns) ? $sort_by : 'name';
                $sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';
                
                $query .= " ORDER BY $sort_by $sort_order";
                
                $stmt = $db->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode($employees);
            }
            break;
            
        case 'POST':
            // Add new employee
            $name = $input['name'] ?? '';
            $email = $input['email'] ?? '';
            $phone = $input['phone'] ?? '';
            $position = $input['position'] ?? '';
            
            if (empty($name) || empty($email) || empty($position)) {
                http_response_code(400);
                echo json_encode(['error' => 'Name, email, and position are required']);
                return;
            }
            
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM employees WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Email already exists']);
                return;
            }
            
            $query = "INSERT INTO employees (name, email, phone, position) VALUES (:name, :email, :phone, :position)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':position', $position);
            
            if ($stmt->execute()) {
                $id = $db->lastInsertId();
                http_response_code(201);
                echo json_encode([
                    'success' => true, 
                    'id' => $id, 
                    'message' => 'Employee added successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error adding employee']);
            }
            break;
            
        case 'PUT':
            // Update employee
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Employee ID required']);
                return;
            }
            
            $name = $input['name'] ?? '';
            $email = $input['email'] ?? '';
            $phone = $input['phone'] ?? '';
            $position = $input['position'] ?? '';
            
            // Check if employee exists
            $stmt = $db->prepare("SELECT id FROM employees WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Employee not found']);
                return;
            }
            
            // Check if email already exists (excluding current employee)
            $stmt = $db->prepare("SELECT id FROM employees WHERE email = :email AND id != :id");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Email already exists']);
                return;
            }
            
            $query = "UPDATE employees SET name = :name, email = :email, phone = :phone, position = :position WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':position', $position);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error updating employee']);
            }
            break;
            
        case 'DELETE':
            // Delete employee
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Employee ID required']);
                return;
            }
            
            // Check if employee is assigned to any tasks
            $stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $task_count = $stmt->fetchColumn();
            
            if ($task_count > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot delete employee assigned to tasks']);
                return;
            }
            
            // Check if employee is assigned to any activities
            $stmt = $db->prepare("SELECT COUNT(*) FROM activities WHERE assigned_to = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $activity_count = $stmt->fetchColumn();
            
            if ($activity_count > 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot delete employee assigned to activities']);
                return;
            }
            
            $query = "DELETE FROM employees WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Employee deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error deleting employee']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function handleTasks($db, $method, $path_segments, $input, $query_params) {
    $id = isset($path_segments[1]) && is_numeric($path_segments[1]) ? intval($path_segments[1]) : null;

    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single task
                $stmt = $db->prepare("
                    SELECT t.*, e.name as assigned_name 
                    FROM tasks t 
                    LEFT JOIN employees e ON t.assigned_to = e.id 
                    WHERE t.id = :id
                ");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $task = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($task) {
                    echo json_encode($task);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Task not found']);
                }
            } else {
                // Get all tasks with filters
                $search = $query_params['search'] ?? '';
                $status_filter = $query_params['status'] ?? '';
                $priority_filter = $query_params['priority'] ?? '';
                $assigned_filter = $query_params['assigned'] ?? '';
                $sort_by = $query_params['sort_by'] ?? 'created_at';
                $sort_order = $query_params['sort_order'] ?? 'DESC';
                
                $query = "
                    SELECT t.*, e.name as assigned_name 
                    FROM tasks t 
                    LEFT JOIN employees e ON t.assigned_to = e.id 
                    WHERE 1=1
                ";
                $params = [];
                
                if (!empty($search)) {
                    $query .= " AND (t.title LIKE :search OR t.description LIKE :search OR e.name LIKE :search)";
                    $params[':search'] = "%$search%";
                }
                
                if (!empty($status_filter)) {
                    $query .= " AND t.status = :status";
                    $params[':status'] = $status_filter;
                }
                
                if (!empty($priority_filter)) {
                    $query .= " AND t.priority = :priority";
                    $params[':priority'] = $priority_filter;
                }
                
                if (!empty($assigned_filter)) {
                    $query .= " AND t.assigned_to = :assigned";
                    $params[':assigned'] = $assigned_filter;
                }
                
                // Add sorting
                $valid_sort_columns = ['title', 'priority', 'status', 'progress', 'due_date', 'created_at', 'assigned_name'];
                $sort_by = in_array($sort_by, $valid_sort_columns) ? $sort_by : 'created_at';
                $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
                
                if ($sort_by === 'assigned_name') {
                    $query .= " ORDER BY e.name $sort_order";
                } else {
                    $query .= " ORDER BY t.$sort_by $sort_order";
                }
                
                $stmt = $db->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode($tasks);
            }
            break;
            
        case 'POST':
            // Add new task
            $title = $input['title'] ?? '';
            $description = $input['description'] ?? '';
            $assigned_to = !empty($input['assigned_to']) ? intval($input['assigned_to']) : null;
            $priority = $input['priority'] ?? 'Medium';
            $due_date = !empty($input['due_date']) ? $input['due_date'] : null;
            
            if (empty($title)) {
                http_response_code(400);
                echo json_encode(['error' => 'Title is required']);
                return;
            }
            
            // Validate assigned_to if provided
            if ($assigned_to) {
                $stmt = $db->prepare("SELECT id FROM employees WHERE id = :id");
                $stmt->bindParam(':id', $assigned_to);
                $stmt->execute();
                
                if ($stmt->rowCount() === 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid employee assignment']);
                    return;
                }
            }
            
            // Validate priority
            $valid_priorities = ['Low', 'Medium', 'High'];
            if (!in_array($priority, $valid_priorities)) {
                $priority = 'Medium';
            }
            
            $query = "INSERT INTO tasks (title, description, assigned_to, priority, due_date) 
                      VALUES (:title, :description, :assigned_to, :priority, :due_date)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':assigned_to', $assigned_to);
            $stmt->bindParam(':priority', $priority);
            $stmt->bindParam(':due_date', $due_date);
            
            if ($stmt->execute()) {
                $id = $db->lastInsertId();
                http_response_code(201);
                echo json_encode([
                    'success' => true, 
                    'id' => $id, 
                    'message' => 'Task added successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error adding task']);
            }
            break;
            
        case 'PUT':
            // Update task
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID required']);
                return;
            }
            
            $progress = isset($input['progress']) ? intval($input['progress']) : null;
            $status = $input['status'] ?? null;
            $title = $input['title'] ?? null;
            $description = $input['description'] ?? null;
            $assigned_to = isset($input['assigned_to']) ? intval($input['assigned_to']) : null;
            $priority = $input['priority'] ?? null;
            $due_date = $input['due_date'] ?? null;
            
            // Check if task exists
            $stmt = $db->prepare("SELECT id FROM tasks WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Task not found']);
                return;
            }
            
            // Build dynamic update query
            $update_fields = [];
            $params = [':id' => $id];
            
            if (!is_null($progress)) {
                $update_fields[] = "progress = :progress";
                $params[':progress'] = max(0, min(100, $progress));
            }
            
            if (!is_null($status)) {
                $valid_statuses = ['Pending', 'In Progress', 'Completed'];
                if (in_array($status, $valid_statuses)) {
                    $update_fields[] = "status = :status";
                    $params[':status'] = $status;
                }
            }
            
            if (!is_null($title)) {
                $update_fields[] = "title = :title";
                $params[':title'] = $title;
            }
            
            if (!is_null($description)) {
                $update_fields[] = "description = :description";
                $params[':description'] = $description;
            }
            
            if (!is_null($assigned_to)) {
                if ($assigned_to === 0) {
                    $update_fields[] = "assigned_to = NULL";
                } else {
                    // Validate employee exists
                    $stmt = $db->prepare("SELECT id FROM employees WHERE id = :assigned_to");
                    $stmt->bindParam(':assigned_to', $assigned_to);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $update_fields[] = "assigned_to = :assigned_to";
                        $params[':assigned_to'] = $assigned_to;
                    }
                }
            }
            
            if (!is_null($priority)) {
                $valid_priorities = ['Low', 'Medium', 'High'];
                if (in_array($priority, $valid_priorities)) {
                    $update_fields[] = "priority = :priority";
                    $params[':priority'] = $priority;
                }
            }
            
            if (!is_null($due_date)) {
                $update_fields[] = "due_date = :due_date";
                $params[':due_date'] = $due_date;
            }
            
            if (empty($update_fields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                return;
            }
            
            $query = "UPDATE tasks SET " . implode(', ', $update_fields) . " WHERE id = :id";
            $stmt = $db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error updating task']);
            }
            break;
            
        case 'DELETE':
            // Delete task
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID required']);
                return;
            }
            
            $query = "DELETE FROM tasks WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error deleting task']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function handleActivities($db, $method, $path_segments, $input, $query_params) {
    $id = isset($path_segments[1]) && is_numeric($path_segments[1]) ? intval($path_segments[1]) : null;

    switch ($method) {
        case 'GET':
            if ($id) {
                // Get single activity
                $stmt = $db->prepare("
                    SELECT a.*, e.name as assigned_name, e.email as assigned_email, e.phone as assigned_phone 
                    FROM activities a 
                    LEFT JOIN employees e ON a.assigned_to = e.id 
                    WHERE a.id = :id
                ");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $activity = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($activity) {
                    echo json_encode($activity);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Activity not found']);
                }
            } else {
                // Get all activities with filters
                $search = $query_params['search'] ?? '';
                $status_filter = $query_params['status'] ?? '';
                $priority_filter = $query_params['priority'] ?? '';
                $sort_by = $query_params['sort_by'] ?? 'created_at';
                $sort_order = $query_params['sort_order'] ?? 'DESC';
                
                $query = "
                    SELECT a.*, e.name as assigned_name 
                    FROM activities a 
                    LEFT JOIN employees e ON a.assigned_to = e.id 
                    WHERE 1=1
                ";
                $params = [];
                
                if (!empty($search)) {
                    $query .= " AND (a.title LIKE :search OR a.description LIKE :search OR a.location LIKE :search OR e.name LIKE :search)";
                    $params[':search'] = "%$search%";
                }
                
                if (!empty($status_filter)) {
                    $query .= " AND a.status = :status";
                    $params[':status'] = $status_filter;
                }
                
                if (!empty($priority_filter)) {
                    $query .= " AND a.priority = :priority";
                    $params[':priority'] = $priority_filter;
                }
                
                // Add sorting
                $valid_sort_columns = ['title', 'location', 'activity_date', 'priority', 'status', 'created_at'];
                $sort_by = in_array($sort_by, $valid_sort_columns) ? $sort_by : 'created_at';
                $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
                
                $query .= " ORDER BY a.$sort_by $sort_order";
                
                $stmt = $db->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode($activities);
            }
            break;
            
        case 'POST':
            // Add new activity
            $title = $input['title'] ?? '';
            $description = $input['description'] ?? '';
            $location = $input['location'] ?? '';
            $activity_date = $input['activity_date'] ?? date('Y-m-d');
            $assigned_to = !empty($input['assigned_to']) ? intval($input['assigned_to']) : null;
            $priority = $input['priority'] ?? 'Medium';
            
            if (empty($title) || empty($location)) {
                http_response_code(400);
                echo json_encode(['error' => 'Title and location are required']);
                return;
            }
            
            // Validate assigned_to if provided
            if ($assigned_to) {
                $stmt = $db->prepare("SELECT id FROM employees WHERE id = :id");
                $stmt->bindParam(':id', $assigned_to);
                $stmt->execute();
                
                if ($stmt->rowCount() === 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid employee assignment']);
                    return;
                }
            }
            
            // Validate priority
            $valid_priorities = ['Low', 'Medium', 'High'];
            if (!in_array($priority, $valid_priorities)) {
                $priority = 'Medium';
            }
            
            // Validate date
            if (!validateDate($activity_date)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
                return;
            }
            
            $query = "INSERT INTO activities (title, description, location, activity_date, assigned_to, priority, status) 
                      VALUES (:title, :description, :location, :activity_date, :assigned_to, :priority, 'Pending')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':activity_date', $activity_date);
            $stmt->bindParam(':assigned_to', $assigned_to);
            $stmt->bindParam(':priority', $priority);
            
            if ($stmt->execute()) {
                $activity_id = $db->lastInsertId();
                
                // Also create a corresponding task if assigned to someone
                if ($assigned_to) {
                    $task_query = "INSERT INTO tasks (title, description, assigned_to, priority, status, due_date) 
                                   VALUES (:title, :description, :assigned_to, :priority, 'Pending', :due_date)";
                    $task_stmt = $db->prepare($task_query);
                    $task_stmt->bindParam(':title', $title);
                    $task_stmt->bindParam(':description', $description);
                    $task_stmt->bindParam(':assigned_to', $assigned_to);
                    $task_stmt->bindParam(':priority', $priority);
                    $task_stmt->bindParam(':due_date', $activity_date);
                    $task_stmt->execute();
                }
                
                http_response_code(201);
                echo json_encode([
                    'success' => true, 
                    'id' => $activity_id, 
                    'message' => 'Activity recorded successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error recording activity']);
            }
            break;
            
        case 'PUT':
            // Update activity
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Activity ID required']);
                return;
            }
            
            $title = $input['title'] ?? null;
            $description = $input['description'] ?? null;
            $location = $input['location'] ?? null;
            $activity_date = $input['activity_date'] ?? null;
            $assigned_to = isset($input['assigned_to']) ? intval($input['assigned_to']) : null;
            $priority = $input['priority'] ?? null;
            $status = $input['status'] ?? null;
            
            // Check if activity exists
            $stmt = $db->prepare("SELECT id FROM activities WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Activity not found']);
                return;
            }
            
            // Build dynamic update query
            $update_fields = [];
            $params = [':id' => $id];
            
            if (!is_null($title)) {
                $update_fields[] = "title = :title";
                $params[':title'] = $title;
            }
            
            if (!is_null($description)) {
                $update_fields[] = "description = :description";
                $params[':description'] = $description;
            }
            
            if (!is_null($location)) {
                $update_fields[] = "location = :location";
                $params[':location'] = $location;
            }
            
            if (!is_null($activity_date)) {
                if (!validateDate($activity_date)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
                    return;
                }
                $update_fields[] = "activity_date = :activity_date";
                $params[':activity_date'] = $activity_date;
            }
            
            if (!is_null($assigned_to)) {
                if ($assigned_to === 0) {
                    $update_fields[] = "assigned_to = NULL";
                } else {
                    // Validate employee exists
                    $stmt = $db->prepare("SELECT id FROM employees WHERE id = :assigned_to");
                    $stmt->bindParam(':assigned_to', $assigned_to);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $update_fields[] = "assigned_to = :assigned_to";
                        $params[':assigned_to'] = $assigned_to;
                    }
                }
            }
            
            if (!is_null($priority)) {
                $valid_priorities = ['Low', 'Medium', 'High'];
                if (in_array($priority, $valid_priorities)) {
                    $update_fields[] = "priority = :priority";
                    $params[':priority'] = $priority;
                }
            }
            
            if (!is_null($status)) {
                $valid_statuses = ['Pending', 'In Progress', 'Completed'];
                if (in_array($status, $valid_statuses)) {
                    $update_fields[] = "status = :status";
                    $params[':status'] = $status;
                }
            }
            
            if (empty($update_fields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                return;
            }
            
            $query = "UPDATE activities SET " . implode(', ', $update_fields) . " WHERE id = :id";
            $stmt = $db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Activity updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error updating activity']);
            }
            break;
            
        case 'DELETE':
            // Delete activity
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Activity ID required']);
                return;
            }
            
            $query = "DELETE FROM activities WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Activity deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error deleting activity']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

function handleReports($db, $method, $input, $query_params) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $report_type = $input['report_type'] ?? 'maintenance_summary';
    $start_date = $input['start_date'] ?? '';
    $end_date = $input['end_date'] ?? date('Y-m-d');
    $employee_id = $input['employee_id'] ?? '';
    
    try {
        $report_data = generateReport($db, $report_type, $start_date, $end_date, $employee_id);
        echo json_encode($report_data);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error generating report: ' . $e->getMessage()]);
    }
}

function handleUsers($db, $method, $path_segments, $input, $query_params) {
    // This endpoint is for user management (admin functionality)
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    // For now, just return current user info
    if (isset($_SESSION['user_data'])) {
        echo json_encode($_SESSION['user_data']);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
    }
}

// Helper functions
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function generateReport($db, $report_type, $start_date, $end_date, $employee_id) {
    $params = [];
    
    // Validate date format if provided
    if (!empty($start_date) && !validateDate($start_date)) {
        throw new Exception("Invalid start date format");
    }
    
    if (!empty($end_date) && !validateDate($end_date)) {
        throw new Exception("Invalid end date format");
    }
    
    // Build query based on report type
    switch ($report_type) {
        case 'employee_performance':
            $query = "
                SELECT 
                    e.name, 
                    e.position,
                    COUNT(t.id) as total_tasks,
                    SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN t.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                    SUM(CASE WHEN t.status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks,
                    ROUND(COALESCE(SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(t.id), 0), 0), 0) as completion_rate,
                    ROUND(AVG(t.progress), 2) as avg_progress
                FROM employees e
                LEFT JOIN tasks t ON e.id = t.assigned_to
            ";
            
            $conditions = [];
            if (!empty($start_date)) {
                $conditions[] = "t.created_at >= :start_date";
                $params[':start_date'] = $start_date;
            }
            if (!empty($end_date)) {
                $conditions[] = "t.created_at <= :end_date";
                $params[':end_date'] = $end_date;
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " GROUP BY e.id, e.name, e.position ORDER BY completion_rate DESC";
            break;
            
        case 'task_completion':
            $query = "
                SELECT 
                    t.id,
                    t.title, 
                    t.priority, 
                    t.status, 
                    t.progress, 
                    t.due_date, 
                    t.created_at, 
                    t.completed_at,
                    e.name as assigned_name,
                    e.position as assigned_position
                FROM tasks t
                LEFT JOIN employees e ON t.assigned_to = e.id
                WHERE 1=1
            ";
            
            if (!empty($start_date)) {
                $query .= " AND t.created_at >= :start_date";
                $params[':start_date'] = $start_date;
            }
            if (!empty($end_date)) {
                $query .= " AND t.created_at <= :end_date";
                $params[':end_date'] = $end_date;
            }
            if (!empty($employee_id)) {
                $query .= " AND t.assigned_to = :employee_id";
                $params[':employee_id'] = $employee_id;
            }
            
            $query .= " ORDER BY t.created_at DESC";
            break;
            
        case 'activity_summary':
            $query = "
                SELECT 
                    a.id,
                    a.title,
                    a.location,
                    a.activity_date,
                    a.priority,
                    a.status,
                    a.created_at,
                    e.name as assigned_name,
                    e.position as assigned_position
                FROM activities a
                LEFT JOIN employees e ON a.assigned_to = e.id
                WHERE 1=1
            ";
            
            if (!empty($start_date)) {
                $query .= " AND a.activity_date >= :start_date";
                $params[':start_date'] = $start_date;
            }
            if (!empty($end_date)) {
                $query .= " AND a.activity_date <= :end_date";
                $params[':end_date'] = $end_date;
            }
            if (!empty($employee_id)) {
                $query .= " AND a.assigned_to = :employee_id";
                $params[':employee_id'] = $employee_id;
            }
            
            $query .= " ORDER BY a.activity_date DESC";
            break;
            
        case 'maintenance_summary':
        default:
            $query = "
                SELECT 
                    'Tasks' as category,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                    ROUND(AVG(progress), 2) as avg_progress
                FROM tasks
                WHERE 1=1
            ";
            
            if (!empty($start_date)) {
                $query .= " AND created_at >= :start_date";
                $params[':start_date'] = $start_date;
            }
            if (!empty($end_date)) {
                $query .= " AND created_at <= :end_date";
                $params[':end_date'] = $end_date;
            }
            
            $query .= "
                UNION ALL
                SELECT 
                    'Activities' as category,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                    0 as avg_progress
                FROM activities
                WHERE 1=1
            ";
            
            if (!empty($start_date)) {
                $query .= " AND activity_date >= :start_date2";
                $params[':start_date2'] = $start_date;
            }
            if (!empty($end_date)) {
                $query .= " AND activity_date <= :end_date2";
                $params[':end_date2'] = $end_date;
            }
            break;
    }
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>