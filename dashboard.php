<?php
require_once 'auth.php';
redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

// Get counts for dashboard
$employee_count = $db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$task_count = $db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$completed_tasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'Completed'")->fetchColumn();
$pending_tasks = $db->query("SELECT COUNT(*) FROM tasks WHERE status = 'Pending'")->fetchColumn();

// Get recent tasks
$recent_tasks = $db->query("
    SELECT t.*, e.name as assigned_name 
    FROM tasks t 
    LEFT JOIN employees e ON t.assigned_to = e.id 
    ORDER BY t.created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get employee performance
$employee_performance = $db->query("
    SELECT e.name, 
           COUNT(t.id) as total_tasks,
           SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
           ROUND(COALESCE(SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(t.id), 0), 0), 0) as completion_rate
    FROM employees e
    LEFT JOIN tasks t ON e.id = t.assigned_to
    GROUP BY e.id
    ORDER BY completion_rate DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Maintenance Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
     <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'header.php'; ?>
        
        <div class="content">
            <h2 class="mb-4">Dashboard</h2>
            
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card card-stat bg-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary p-3 rounded me-3">
                                    <i class="fas fa-users fa-2x text-white"></i>
                                </div>
                                <div>
                                    <h6 class="card-title mb-0">Total Employees</h6>
                                    <h3 class="mb-0"><?php echo $employee_count; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card card-stat bg-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="bg-success p-3 rounded me-3">
                                    <i class="fas fa-tasks fa-2x text-white"></i>
                                </div>
                                <div>
                                    <h6 class="card-title mb-0">Total Tasks</h6>
                                    <h3 class="mb-0"><?php echo $task_count; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card card-stat bg-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="bg-warning p-3 rounded me-3">
                                    <i class="fas fa-clock fa-2x text-white"></i>
                                </div>
                                <div>
                                    <h6 class="card-title mb-0">Pending Tasks</h6>
                                    <h3 class="mb-0"><?php echo $pending_tasks; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card card-stat bg-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="bg-info p-3 rounded me-3">
                                    <i class="fas fa-check-circle fa-2x text-white"></i>
                                </div>
                                <div>
                                    <h6 class="card-title mb-0">Completed Tasks</h6>
                                    <h3 class="mb-0"><?php echo $completed_tasks; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8 mb-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Recent Tasks</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Task</th>
                                            <th>Assigned To</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_tasks as $task): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($task['title']); ?></td>
                                            <td><?php echo htmlspecialchars($task['assigned_name'] ?? 'Unassigned'); ?></td>
                                            <td><span class="badge bg-<?php 
                                                echo $task['priority'] == 'High' ? 'danger' : 
                                                    ($task['priority'] == 'Medium' ? 'warning' : 'success'); 
                                            ?>"><?php echo $task['priority']; ?></span></td>
                                            <td><span class="badge bg-<?php 
                                                echo $task['status'] == 'Completed' ? 'success' : 
                                                    ($task['status'] == 'In Progress' ? 'info' : 'warning'); 
                                            ?>"><?php echo $task['status']; ?></span></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-<?php 
                                                        echo $task['status'] == 'Completed' ? 'success' : 
                                                            ($task['status'] == 'In Progress' ? 'info' : 'warning'); 
                                                    ?>" style="width: <?php echo $task['progress']; ?>%"></div>
                                                </div>
                                                <small><?php echo $task['progress']; ?>%</small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Employee Performance</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($employee_performance as $employee): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo htmlspecialchars($employee['name']); ?></span>
                                    <span><?php echo $employee['completion_rate']; ?>%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: <?php echo $employee['completion_rate']; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $employee['completed_tasks']; ?> of <?php echo $employee['total_tasks']; ?> tasks completed</small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>