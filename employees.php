<?php
require_once 'auth.php';
redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_employee'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $position = $_POST['position'];
        
        $query = "INSERT INTO employees (name, email, phone, position) VALUES (:name, :email, :phone, :position)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':position', $position);
        
        if ($stmt->execute()) {
            $success = "Employee added successfully!";
        } else {
            $error = "Error adding employee.";
        }
    } elseif (isset($_POST['delete_employee'])) {
        $id = $_POST['id'];
        
        $query = "DELETE FROM employees WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $success = "Employee deleted successfully!";
        } else {
            $error = "Error deleting employee.";
        }
    }
}

// Search and filter parameters
$search = $_GET['search'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'name';
$sort_order = $_GET['sort_order'] ?? 'ASC';

// Build query for employees with filters
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

// Get employees with filters
try {
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error loading employees: " . $e->getMessage();
    $employees = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - Maintenance Management System</title>
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
            <h2 class="mb-4">Employee Management</h2>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Add New Employee</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <div class="d-flex align-items-center">
                                    <input type="text" class="form-control" name="name" required>
                                    <i class="fas fa-question-circle tooltip-icon" 
                                       data-bs-toggle="tooltip" 
                                       title="Enter the employee's full name (first and last name)"></i>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <div class="d-flex align-items-center">
                                    <input type="email" class="form-control" name="email" required>
                                    <i class="fas fa-question-circle tooltip-icon" 
                                       data-bs-toggle="tooltip" 
                                       title="Enter a valid email address for the employee"></i>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <div class="d-flex align-items-center">
                                    <input type="tel" class="form-control" name="phone">
                                    <i class="fas fa-question-circle tooltip-icon" 
                                       data-bs-toggle="tooltip" 
                                       title="Enter the employee's phone number (optional)"></i>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Position</label>
                                <div class="d-flex align-items-center">
                                    <input type="text" class="form-control" name="position" required>
                                    <i class="fas fa-question-circle tooltip-icon" 
                                       data-bs-toggle="tooltip" 
                                       title="Enter the employee's job title or position"></i>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="add_employee" class="btn btn-primary">Add Employee</button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">All Employees</h5>
                </div>
                <div class="card-body">
                    <!-- Search and Filter Form -->
                    <form method="GET" class="search-form mb-4">
                        <div class="row">
                            <div class="col-md-8 mb-2">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search employees by name, email, position, or phone...">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Sort By</label>
                                <select class="form-select" name="sort_by">
                                    <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name</option>
                                    <option value="position" <?php echo $sort_by == 'position' ? 'selected' : ''; ?>>Position</option>
                                    <option value="email" <?php echo $sort_by == 'email' ? 'selected' : ''; ?>>Email</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label">Order</label>
                                <select class="form-select" name="sort_order">
                                    <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                    <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                <?php if ($search): ?>
                                <a href="employees.php" class="btn btn-outline-secondary">Clear Filters</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th class="sortable-header" onclick="sortTable('id')">ID</th>
                                    <th class="sortable-header" onclick="sortTable('name')">Name</th>
                                    <th class="sortable-header" onclick="sortTable('email')">Email</th>
                                    <th class="sortable-header" onclick="sortTable('phone')">Phone</th>
                                    <th class="sortable-header" onclick="sortTable('position')">Position</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($employees) > 0): ?>
                                    <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td><?php echo $employee['id']; ?></td>
                                        <td><?php echo htmlspecialchars($employee['name']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
                                                <button type="submit" name="delete_employee" class="btn btn-sm btn-outline-danger" 
                                                        data-bs-toggle="tooltip" title="Delete this employee permanently"
                                                        onclick="return confirm('Are you sure you want to delete this employee?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No employees found matching your criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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
            
            window.location.href = 'employees.php?' + urlParams.toString();
        }
    </script>
</body>
</html>