<div class="header">
    <h5 id="current-section">
        <div class="icon-circle icon-circle-primary">
            <i class="fas fa-<?php
                $page = basename($_SERVER['PHP_SELF']);
                switch ($page) {
                    case 'dashboard.php': echo 'tachometer-alt'; break;
                    case 'activities.php': echo 'clipboard-list'; break;
                    case 'employees.php': echo 'users'; break;
                    case 'tasks.php': echo 'tasks'; break;
                    case 'reports.php': echo 'chart-bar'; break;
                    default: echo 'cog';
                }
            ?>"></i>
        </div>
        <?php
            switch ($page) {
                case 'dashboard.php': echo 'Dashboard'; break;
                case 'activities.php': echo 'Record Activities'; break;
                case 'employees.php': echo 'Employee Management'; break;
                case 'tasks.php': echo 'Task Management'; break;
                case 'reports.php': echo 'Reports'; break;
                default: echo 'Maintenance System';
            }
        ?>
    </h5>
    <div class="d-flex align-items-center">
        <div class="me-3 text-end">
            <div class="fw-bold"><?php echo $_SESSION['username']; ?></div>
            <div class="real-time-display">
                <span id="current-time" class="time-display"></span>
                <span id="current-date" class="date-display"></span>
            </div>
        </div>
        <div class="avatar avatar-primary" data-bs-toggle="tooltip" title="Logged in as <?php echo $_SESSION['username']; ?>">
            <i class="fas fa-user-cog"></i>
        </div>
    </div>
</div>

<style>
    .real-time-display {
        font-size: 0.8rem;
        line-height: 1.2;
    }
    
    .time-display {
        font-weight: 600;
        color: #00264d;
        display: block;
    }
    
    .date-display {
        color: #6c757d;
        font-size: 0.75rem;
    }
    
    @media (max-width: 768px) {
        .real-time-display {
            display: none;
        }
    }
</style>

<script>
    function updateDateTime() {
        const now = new Date();
        
        // Format time (HH:MM:SS AM/PM)
        const timeOptions = { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit',
            hour12: true 
        };
        const timeString = now.toLocaleTimeString('en-US', timeOptions);
        
        // Format date (Day, Month Date, Year)
        const dateOptions = { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        };
        const dateString = now.toLocaleDateString('en-US', dateOptions);
        
        // Update the elements
        document.getElementById('current-time').textContent = timeString;
        document.getElementById('current-date').textContent = dateString;
    }
    
    // Update immediately and then every second
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Also update when the page becomes visible again
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            updateDateTime();
        }
    });
</script>