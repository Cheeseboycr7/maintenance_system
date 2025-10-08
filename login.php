<?php
require_once 'auth.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        if (loginUser($username, $password)) {
             header('Location:splash.php');
            exit();
        } else {
            $error = "Invalid username or password";
        }
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage() . 
                 ". Please run the <a href='install.php'>installation script</a> first.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Maintenance Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
     <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
</head>
<body class="login-container">
    <div class="login-form">
        <div class="login-header">
          <div class="sidebar-logo">
            <img style="width: 80px;" src="twr.png" alt="">
        </div>
            <p class="splash-tagline">Speaking Hope To The World</p>
            <h1 class="company-name">Maintenance Pro</h1>
            <p class="company-tagline">Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">
                    <i class="fas fa-user me-1"></i> Username
                </label>
                <input type="text" class="form-control" id="username" name="username" required 
                       placeholder="Enter your username">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="fas fa-lock me-1"></i> Password
                </label>
                <input type="password" class="form-control" id="password" name="password" required 
                       placeholder="Enter your password">
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </button>
            
            <div class="mt-3 text-center">
                <small class="text-muted">
                    Default credentials: <strong>admin</strong> / <strong>admin123</strong>
                    <i class="fas fa-question-circle tooltip-icon" 
                       data-bs-toggle="tooltip" 
                       title="Use these credentials for first-time login"></i>
                </small>
            </div>
        </form>
        
        <div class="mt-4 text-center">
            <small>First time setup? <a href="install.php" class="secondary-text">Install database</a></small>
        </div>
        <div class="mt-2 text-center">
            <small>Password not working? <a href="reset_password.php" class="secondary-text">Reset password</a></small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html>