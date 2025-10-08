<?php
require_once 'auth.php';
redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

// Get employees for assignment dropdown
$employees = $db->query("SELECT * FROM employees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_activity'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $location = $_POST['location'];
        $activity_date = $_POST['activity_date'];
        $assigned_to = $_POST['assigned_to'] ?: null;
        $priority = $_POST['priority'];
        $status = 'Pending'; // Default status for new activities
        
        try {
            $query = "INSERT INTO activities (title, description, location, activity_date, assigned_to, priority, status) 
                      VALUES (:title, :description, :location, :activity_date, :assigned_to, :priority, :status)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':activity_date', $activity_date);
            $stmt->bindParam(':assigned_to', $assigned_to);
            $stmt->bindParam(':priority', $priority);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                $success = "Activity recorded successfully!";
                
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
            } else {
                $error = "Error recording activity.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    // Handle email sending - FIXED: Check if email fields exist before accessing them
    if (isset($_POST['send_email'])) {
        // Check if all required email fields are set
        if (isset($_POST['email'], $_POST['email_subject'], $_POST['email_message'], $_POST['employee_name'])) {
            $to_email = $_POST['email'];
            $subject = $_POST['email_subject'];
            $message = $_POST['email_message'];
            $employee_name = $_POST['employee_name'];
            
            // Validate email format
            if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email address format.";
            } else {
                // Basic email headers
                $headers = "From: maintenance@yourcompany.com\r\n";
                $headers .= "Reply-To: maintenance@yourcompany.com\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                
                // Create HTML email
                $html_message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                        .content { padding: 20px; }
                        .footer { background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class='header'>
                        <h2>Maintenance Management System</h2>
                    </div>
                    <div class='content'>
                        <h3>Hello $employee_name,</h3>
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>
                    <div class='footer'>
                        <p>This is an automated message from the Maintenance Management System</p>
                    </div>
                </body>
                </html>
                ";
                
                // Send email
                if (mail($to_email, $subject, $html_message, $headers)) {
                    $success = "Email sent successfully to $employee_name!";
                } else {
                    $error = "Failed to send email. Please check your server email configuration.";
                }
            }
        } else {
            $error = "Please fill in all required email fields.";
        }
    }
}

// Get activity details if ID is provided
$activity_details = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $activity_id = $_GET['view'];
    try {
        $stmt = $db->prepare("
            SELECT a.*, e.name as assigned_name, e.email as assigned_email, e.phone as assigned_phone 
            FROM activities a 
            LEFT JOIN employees e ON a.assigned_to = e.id 
            WHERE a.id = :id
        ");
        $stmt->bindParam(':id', $activity_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $activity_details = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Activity not found.";
        }
    } catch (Exception $e) {
        $error = "Error retrieving activity details: " . $e->getMessage();
    }
}

// Search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'created_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';

// Build query for activities with filters
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

// Get activities with filters
try {
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error loading activities: " . $e->getMessage();
    $activities = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Activities - Maintenance Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .activity-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .detail-item {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .detail-label {
            font-weight: 600;
            color: #495057;
        }
        .activity-card {
            transition: all 0.2s;
            cursor: pointer;
        }
        .activity-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.8em;
            padding: 0.4em 0.8em;
        }
        .back-link {
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
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
        .email-modal textarea {
            min-height: 150px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'header.php'; ?>
        
        <div class="content">
            <h2 class="mb-4">Record Maintenance Activities</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Record New Activity</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Activity Title *</label>
                                    <div class="d-flex align-items-center">
                                        <input type="text" class="form-control" name="title" required>
                                        <i class="fas fa-question-circle tooltip-icon" 
                                           data-bs-toggle="tooltip" 
                                           title="Enter a descriptive title for the maintenance activity"></i>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <div class="d-flex align-items-center">
                                        <textarea class="form-control" name="description" rows="3"></textarea>
                                        <i class="fas fa-question-circle tooltip-icon" 
                                           data-bs-toggle="tooltip" 
                                           title="Provide detailed information about the maintenance activity"></i>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Location *</label>
                                    <div class="d-flex align-items-center">
                                        <input type="text" class="form-control" name="location" required>
                                        <i class="fas fa-question-circle tooltip-icon" 
                                           data-bs-toggle="tooltip" 
                                           title="Specify where this maintenance activity will take place"></i>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Activity Date *</label>
                                    <div class="d-flex align-items-center">
                                        <input type="date" class="form-control" name="activity_date" required value="<?php echo date('Y-m-d'); ?>">
                                        <i class="fas fa-question-circle tooltip-icon" 
                                           data-bs-toggle="tooltip" 
                                           title="Select the date when this maintenance activity is scheduled"></i>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Assign To (Optional)</label>
                                    <div class="d-flex align-items-center">
                                        <select class="form-select" name="assigned_to">
                                            <option value="">Select Employee</option>
                                            <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="fas fa-question-circle tooltip-icon" 
                                           data-bs-toggle="tooltip" 
                                           title="Optionally assign this activity to a specific employee"></i>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Priority</label>
                                    <div class="d-flex align-items-center">
                                        <select class="form-select" name="priority">
                                            <option value="Low">Low</option>
                                            <option value="Medium" selected>Medium</option>
                                            <option value="High">High</option>
                                        </select>
                                        <i class="fas fa-question-circle tooltip-icon" 
                                           data-bs-toggle="tooltip" 
                                           title="Set the priority level for this maintenance activity"></i>
                                    </div>
                                </div>
                                <button type="submit" name="add_activity" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Record Activity
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Quick Email Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Quick Email to Employee</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Select Employee</label>
                                    <select class="form-select" name="employee_email" required>
                                        <option value="">Choose Employee</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <?php if (!empty($employee['email'])): ?>
                                            <option value="<?php echo $employee['email']; ?>" data-name="<?php echo htmlspecialchars($employee['name']); ?>">
                                                <?php echo htmlspecialchars($employee['name']); ?> (<?php echo htmlspecialchars($employee['email']); ?>)
                                            </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Subject</label>
                                    <input type="text" class="form-control" name="email_subject" value="Maintenance Activity Update" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Message</label>
                                    <textarea class="form-control" name="email_message" rows="4" required placeholder="Type your message here..."></textarea>
                                </div>
                                <input type="hidden" name="email" id="quickEmail">
                                <input type="hidden" name="employee_name" id="quickEmployeeName">
                                <button type="submit" name="send_email" class="btn btn-success">
                                    <i class="fas fa-paper-plane me-1"></i> Send Email
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <?php if ($activity_details): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Activity Details</h5>
                            <a href="Create activities.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Activities
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="activity-details">
                                <div class="detail-item">
                                    <span class="detail-label">Title:</span>
                                    <div><?php echo htmlspecialchars($activity_details['title']); ?></div>
                                </div>
                                
                                <?php if (!empty($activity_details['description'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Description:</span>
                                    <div><?php echo nl2br(htmlspecialchars($activity_details['description'])); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Location:</span>
                                    <div><?php echo htmlspecialchars($activity_details['location']); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Date:</span>
                                    <div><?php echo date('F j, Y', strtotime($activity_details['activity_date'])); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Priority:</span>
                                    <span class="badge status-badge bg-<?php 
                                        echo $activity_details['priority'] == 'High' ? 'danger' : 
                                            ($activity_details['priority'] == 'Medium' ? 'warning' : 'success'); 
                                    ?>"><?php echo $activity_details['priority']; ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Status:</span>
                                    <span class="badge status-badge bg-<?php 
                                        echo $activity_details['status'] == 'Completed' ? 'success' : 
                                            ($activity_details['status'] == 'In Progress' ? 'info' : 'warning'); 
                                    ?>"><?php echo $activity_details['status']; ?></span>
                                </div>
                                
                                <?php if ($activity_details['assigned_name']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Assigned To:</span>
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity_details['assigned_name']); ?></strong>
                                        <?php if ($activity_details['assigned_email']): ?>
                                        <br><small>Email: <?php echo htmlspecialchars($activity_details['assigned_email']); ?></small>
                                        <?php endif; ?>
                                        <?php if ($activity_details['assigned_phone']): ?>
                                        <br><small>Phone: <?php echo htmlspecialchars($activity_details['assigned_phone']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="detail-item">
                                    <span class="detail-label">Assigned To:</span>
                                    <div class="text-muted">Not assigned</div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Recorded On:</span>
                                    <div><?php echo date('F j, Y g:i A', strtotime($activity_details['created_at'])); ?></div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <?php if ($activity_details['assigned_to']): ?>
                                <a href="tasks.php?assigned=<?php echo $activity_details['assigned_to']; ?>" class="btn btn-sm btn-outline-primary me-2"
                                   data-bs-toggle="tooltip" title="View all tasks assigned to this employee">
                                    <i class="fas fa-tasks me-1"></i> View Assigned Tasks
                                </a>
                                <?php if ($activity_details['assigned_email']): ?>
                                <button type="button" class="btn btn-sm btn-outline-success me-2" data-bs-toggle="modal" data-bs-target="#emailModal"
                                        data-email="<?php echo htmlspecialchars($activity_details['assigned_email']); ?>"
                                        data-name="<?php echo htmlspecialchars($activity_details['assigned_name']); ?>"
                                        data-activity="<?php echo htmlspecialchars($activity_details['title']); ?>">
                                    <i class="fas fa-envelope me-1"></i> Email Employee
                                </button>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Activities</h5>
                            <span class="badge bg-primary rounded-pill"><?php echo count($activities); ?></span>
                        </div>
                        <div class="card-body">
                            <!-- Search and Filter Form -->
                            <form method="GET" class="search-form mb-4">
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Search</label>
                                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                               placeholder="Search activities...">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="">All Status</option>
                                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label">Priority</label>
                                        <select class="form-select" name="priority">
                                            <option value="">All Priorities</option>
                                            <option value="Low" <?php echo $priority_filter == 'Low' ? 'selected' : ''; ?>>Low</option>
                                            <option value="Medium" <?php echo $priority_filter == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="High" <?php echo $priority_filter == 'High' ? 'selected' : ''; ?>>High</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Sort By</label>
                                        <select class="form-select" name="sort_by">
                                            <option value="created_at" <?php echo $sort_by == 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                                            <option value="activity_date" <?php echo $sort_by == 'activity_date' ? 'selected' : ''; ?>>Activity Date</option>
                                            <option value="title" <?php echo $sort_by == 'title' ? 'selected' : ''; ?>>Title</option>
                                            <option value="priority" <?php echo $sort_by == 'priority' ? 'selected' : ''; ?>>Priority</option>
                                            <option value="status" <?php echo $sort_by == 'status' ? 'selected' : ''; ?>>Status</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label">Order</label>
                                        <select class="form-select" name="sort_order">
                                            <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descend</option>
                                            <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascend</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                                    </div>
                                </div>
                                <?php if ($search || $status_filter || $priority_filter): ?>
                                <div class="mt-2">
                                    <a href="Create activities.php" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                                </div>
                                <?php endif; ?>
                            </form>
                            
                            <?php if (count($activities) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th class="sortable-header" onclick="sortTable('title')">Title</th>
                                                <th class="sortable-header" onclick="sortTable('location')">Location</th>
                                                <th class="sortable-header" onclick="sortTable('activity_date')">Date</th>
                                                <th class="sortable-header" onclick="sortTable('assigned_name')">Assigned To</th>
                                                <th class="sortable-header" onclick="sortTable('priority')">Priority</th>
                                                <th class="sortable-header" onclick="sortTable('status')">Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($activities as $activity): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($activity['title']); ?></td>
                                                <td><?php echo htmlspecialchars($activity['location']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($activity['activity_date'])); ?></td>
                                                <td><?php echo $activity['assigned_name'] ? htmlspecialchars($activity['assigned_name']) : 'Not assigned'; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $activity['priority'] == 'High' ? 'danger' : 
                                                            ($activity['priority'] == 'Medium' ? 'warning' : 'success'); 
                                                    ?>"><?php echo $activity['priority']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $activity['status'] == 'Completed' ? 'success' : 
                                                            ($activity['status'] == 'In Progress' ? 'info' : 'warning'); 
                                                    ?>"><?php echo $activity['status']; ?></span>
                                                </td>
                                                <td>
                                                    <a href="Create activities.php?view=<?php echo $activity['id']; ?>" class="btn btn-sm btn-outline-primary"
                                                       data-bs-toggle="tooltip" title="View details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($activity['assigned_name']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success ms-1" 
                                                            data-bs-toggle="tooltip" title="Email assigned employee"
                                                            onclick="quickEmail('<?php echo htmlspecialchars($activity['assigned_name']); ?>')">
                                                        <i class="fas fa-envelope"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3 text-center">
                                    <a href="reports.php?type=activities" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-chart-bar me-1"></i> View All Activities Report
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No activities found matching your criteria.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Modal -->
    <div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="emailModalLabel">Send Email to Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="email" id="modalEmail">
                        <input type="hidden" name="employee_name" id="modalEmployeeName">
                        <div class="mb-3">
                            <label class="form-label">To</label>
                            <input type="text" class="form-control" id="modalTo" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="email_subject" id="modalSubject" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="email_message" id="modalMessage" rows="6" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_email" class="btn btn-success">Send Email</button>
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
            
            // Auto-fill email form when employee selection changes
            const employeeSelect = document.querySelector('select[name="employee_email"]');
            if (employeeSelect) {
                employeeSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        const employeeName = selectedOption.getAttribute('data-name');
                        document.querySelector('input[name="email_subject"]').value = `Maintenance Activity Update - ${employeeName}`;
                        document.getElementById('quickEmail').value = selectedOption.value;
                        document.getElementById('quickEmployeeName').value = employeeName;
                    }
                });
            }
        });

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
            
            window.location.href = 'Create activities.php?' + urlParams.toString();
        }

        // Email modal handler
        const emailModal = document.getElementById('emailModal');
        if (emailModal) {
            emailModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const email = button.getAttribute('data-email');
                const name = button.getAttribute('data-name');
                const activity = button.getAttribute('data-activity');
                
                document.getElementById('modalEmail').value = email;
                document.getElementById('modalEmployeeName').value = name;
                document.getElementById('modalTo').value = `${name} <${email}>`;
                document.getElementById('modalSubject').value = `Update on Activity: ${activity}`;
                document.getElementById('modalMessage').value = `Hello ${name},\n\nRegarding the activity "${activity}", I wanted to provide you with an update:\n\n`;
            });
        }

        // Quick email function for table rows
        function quickEmail(employeeName) {
            const employeeSelect = document.querySelector('select[name="employee_email"]');
            for (let option of employeeSelect.options) {
                if (option.textContent.includes(employeeName)) {
                    employeeSelect.value = option.value;
                    employeeSelect.dispatchEvent(new Event('change'));
                    break;
                }
            }
            document.querySelector('select[name="employee_email"]').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>