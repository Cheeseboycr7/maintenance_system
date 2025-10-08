<?php
require_once 'auth.php';
redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

// Get employees for dropdown
$employees = $db->query("SELECT * FROM employees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_task'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $assigned_to = $_POST['assigned_to'] ?: null;
        $priority = $_POST['priority'];
        $due_date = $_POST['due_date'] ?: null;
        
        $query = "INSERT INTO tasks (title, description, assigned_to, priority, due_date) 
                  VALUES (:title, :description, :assigned_to, :priority, :due_date)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':assigned_to', $assigned_to);
        $stmt->bindParam(':priority', $priority);
        $stmt->bindParam(':due_date', $due_date);
        
        if ($stmt->execute()) {
            $success = "Task added successfully!";
        } else {
            $error = "Error adding task.";
        }
    } elseif (isset($_POST['update_task'])) {
        $id = $_POST['id'];
        $progress = $_POST['progress'];
        $status = $_POST['status'];
        
        $query = "UPDATE tasks SET progress = :progress, status = :status WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':progress', $progress);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $success = "Task updated successfully!";
        } else {
            $error = "Error updating task.";
        }
    } elseif (isset($_POST['delete_task'])) {
        $id = $_POST['id'];
        
        $query = "DELETE FROM tasks WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $success = "Task deleted successfully!";
        } else {
            $error = "Error deleting task.";
        }
    }
}

// Search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$assigned_filter = $_GET['assigned'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'created_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';

// Build query for tasks with filters
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

$query .= " ORDER BY t.$sort_by $sort_order";

// Get tasks with filters
try {
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error loading tasks: " . $e->getMessage();
    $tasks = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - Maintenance Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .tooltip-icon {
            color: #6c757d;
            margin-left: 5px;
            cursor: pointer;
        }
        .search-form {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .sortable-header {
            cursor: pointer;
        }
        .sortable-header:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'header.php'; ?>
        
        <div class="content">
            <h2 class="mb-4">Task Management</h2>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Assign New Task</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Task Title</label>
                                <div class="d-flex align-items-center">
                                    <input type="text" class="form-control" name="title" required>
                                    <i class="fas fa-question-circle tooltip-icon" 
                                       data-bs-toggle="tooltip" 
                                       title="Enter a clear and descriptive title for the task"></i>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Assign To</label>
                                <div class="d-flex align-items-center">
                                    <select class="form-select" name="assigned_to">
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-question-circle tooltip-icon" 
                                       data-bs-toggle="tooltip" 
                                       title="Select an employee to assign this task to (optional)"></i>
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Description</label>
                                <div class="d-flex align-items-center">
                                    <textarea class="form-control" name="description" rows="3"></textarea>
                                    <i class="fas fa-question-circle tooltip-icon" 
                                       data-bs-toggle="tooltip" 
                                       title="Provide detailed instructions or information about this task"></i>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Priority</label>
                                <div class="d-flex align-items-center">
                                    <select class="form-select" name="priority">
                                        <option value="Low">Low</option>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="High">High</option>
                                    </select>
                                    <i class="fas fa-question-circle tooltip-icon" 
                                       data-bs-toggle="tooltip" 
                                       title="Set the priority level for this task"></i>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Due Date</label>
                                <div class="d-flex align-items-center">
                                    <input type="date" class="form-control" name="due_date">
                                    <i class="fas fa-question-circle tooltip-icon" 
                                       data-bs-toggle="tooltip" 
                                       title="Set a due date for this task (optional)"></i>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="add_task" class="btn btn-primary">Assign Task</button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">All Tasks</h5>
                </div>
                <div class="card-body">
                    <!-- Search and Filter Form -->
                    <form method="GET" class="search-form mb-4">
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search tasks...">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority">
                                    <option value="">All Priorities</option>
                                    <option value="Low" <?php echo $priority_filter == 'Low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="Medium" <?php echo $priority_filter == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="High" <?php echo $priority_filter == 'High' ? 'selected' : ''; ?>>High</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Assigned To</label>
                                <select class="form-select" name="assigned">
                                    <option value="">All Employees</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" <?php echo $assigned_filter == $employee['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Sort By</label>
                                <select class="form-select" name="sort_by">
                                    <option value="created_at" <?php echo $sort_by == 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                                    <option value="due_date" <?php echo $sort_by == 'due_date' ? 'selected' : ''; ?>>Due Date</option>
                                    <option value="priority" <?php echo $sort_by == 'priority' ? 'selected' : ''; ?>>Priority</option>
                                    <option value="status" <?php echo $sort_by == 'status' ? 'selected' : ''; ?>>Status</option>
                                    <option value="progress" <?php echo $sort_by == 'progress' ? 'selected' : ''; ?>>Progress</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Order</label>
                                <select class="form-select" name="sort_order">
                                    <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                    <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                <?php if ($search || $status_filter || $priority_filter || $assigned_filter): ?>
                                <a href="tasks.php" class="btn btn-outline-secondary">Clear Filters</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th class="sortable-header" onclick="sortTable('title')">Task</th>
                                    <th class="sortable-header" onclick="sortTable('assigned_name')">Assigned To</th>
                                    <th class="sortable-header" onclick="sortTable('priority')">Priority</th>
                                    <th class="sortable-header" onclick="sortTable('status')">Status</th>
                                    <th class="sortable-header" onclick="sortTable('progress')">Progress</th>
                                    <th class="sortable-header" onclick="sortTable('due_date')">Due Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tasks) > 0): ?>
                                    <?php foreach ($tasks as $task): ?>
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
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-<?php 
                                                    echo $task['status'] == 'Completed' ? 'success' : 
                                                        ($task['status'] == 'In Progress' ? 'info' : 'warning'); 
                                                ?>" style="width: <?php echo $task['progress']; ?>%"></div>
                                            </div>
                                            <small><?php echo $task['progress']; ?>%</small>
                                        </td>
                                        <td><?php echo $task['due_date'] ? date('M j, Y', strtotime($task['due_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#updateTaskModal" 
                                                data-id="<?php echo $task['id']; ?>"
                                                data-progress="<?php echo $task['progress']; ?>"
                                                data-status="<?php echo $task['status']; ?>"
                                                data-bs-toggle="tooltip" title="Update task progress and status">
                                                <i class="fas fa-edit"></i> Update
                                            </button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                                                <button type="submit" name="delete_task" class="btn btn-sm btn-outline-danger" 
                                                        data-bs-toggle="tooltip" title="Delete this task permanently"
                                                        onclick="return confirm('Are you sure you want to delete this task?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No tasks found matching your criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Task Modal -->
    <div class="modal fade" id="updateTaskModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Task Progress</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="update_task_id">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="update_status">
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Progress (%)</label>
                            <input type="range" class="form-range" min="0" max="100" step="10" name="progress" id="update_progress" oninput="updateProgressValue(this.value)">
                            <div class="text-center"><span id="progress_value">0</span>%</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_task" class="btn btn-primary">Update Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });
        
        // Update task modal functionality
        var updateTaskModal = document.getElementById('updateTaskModal');
        updateTaskModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var progress = button.getAttribute('data-progress');
            var status = button.getAttribute('data-status');
            
            var modal = this;
            modal.querySelector('#update_task_id').value = id;
            modal.querySelector('#update_progress').value = progress;
            modal.querySelector('#update_status').value = status;
            modal.querySelector('#progress_value').textContent = progress;
        });
        
        function updateProgressValue(value) {
            document.getElementById('progress_value').textContent = value;
        }

        // Sort table function
        function sortTable(column) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort_by');
            const currentOrder = urlParams.get('sort_order');
            
            let newOrder = 'ASC';
            if (currentSort === column && currentOrder === 'ASC') {
                newOrder = 'DESC';
            }
            
            urlParams.set('sort_by', column);
            urlParams.set('sort_order', newOrder);
            
            window.location.href = 'tasks.php?' + urlParams.toString();
        }
    </script>
</body>
</html>