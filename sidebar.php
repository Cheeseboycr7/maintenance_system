<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img style="width: 80px;" src="twr.png" alt="">
        </div>
        <p class="splash-tagline">Speaking Hope To The World</p>
      
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
                <span class="badge bg-info rounded-pill" data-bs-toggle="tooltip" title="Overview of your maintenance operations">®</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'Create activities.php' ? 'active' : ''; ?>" href="Create activities.php">
                <i class="fas fa-clipboard-list"></i> Record Activities
                <span class="badge bg-success rounded-pill" data-bs-toggle="tooltip" title="Log new maintenance activities">+</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : ''; ?>" href="employees.php">
                <i class="fas fa-users"></i> Employees
                <span class="badge bg-primary rounded-pill" data-bs-toggle="tooltip" title="Manage your maintenance team"><?php 
                    $database = new Database();
                    $db = $database->getConnection();
                    $count = $db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
                    echo $count;
                ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : ''; ?>" href="tasks.php">
                <i class="fas fa-tasks"></i> Tasks
                <span class="badge bg-warning rounded-pill" data-bs-toggle="tooltip" title="View and manage maintenance tasks"><?php 
                    $count = $db->query("SELECT COUNT(*) FROM tasks WHERE status != 'Completed'")->fetchColumn();
                    echo $count;
                ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                <i class="fas fa-chart-bar"></i> Reports
                <span class="badge bg-info rounded-pill" data-bs-toggle="tooltip" title="Generate maintenance reports">📊</span>
            </a>
        </li>
        <li class="nav-item mt-5">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
                <span class="badge bg-secondary rounded-pill" data-bs-toggle="tooltip" title="Exit the system">←</span>
            </a>
        </li>
    </ul>
</div>