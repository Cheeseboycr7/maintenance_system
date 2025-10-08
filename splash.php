<?php
// Set a delay for the splash screen (in seconds)
$splash_delay = 20;

// After the delay, redirect to login page
header("Refresh: $splash_delay; url=login.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a365d;
            --secondary: #e67e22;
            --accent: #d35400;
            --light: #f8f9fa;
            --dark: #2c3e50;
        }
        
        .splash-container {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary) 0%, #2c5282 100%);
            position: relative;
            overflow: hidden;
        }
        
        .splash-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(230, 126, 34, 0.1) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(230, 126, 34, 0.1) 0%, transparent 20%);
            background-size: 300px;
        }
        
        .splash-content {
            text-align: center;
            color: white;
            z-index: 1;
            position: relative;
        }
        
        .splash-logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 40px;
            font-weight: bold;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: pulse 2s infinite;
        }
        
        .splash-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            animation: fadeInUp 1s ease-out;
        }
        
        .splash-subtitle {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
            animation: fadeInUp 1s ease-out 0.2s both;
        }
        
        .splash-tagline {
            font-size: 1rem;
            margin-bottom: 40px;
            opacity: 0.8;
            animation: fadeInUp 1s ease-out 0.4s both;
        }
        
        .progress-container {
            width: 200px;
            height: 4px;
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
            margin: 0 auto;
            overflow: hidden;
            animation: fadeInUp 1s ease-out 0.6s both;
        }
        
        .progress-bar {
            height: 100%;
            width: 0%;
            background: var(--secondary);
            border-radius: 4px;
            animation: loading <?php echo $splash_delay; ?>s linear forwards;
        }
        
        .splash-footer {
            position: absolute;
            bottom: 20px;
            width: 100%;
            text-align: center;
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes loading {
            from { width: 0%; }
            to { width: 100%; }
        }
        
        .loading-text {
            margin-top: 15px;
            font-size: 0.9rem;
            opacity: 0.8;
            animation: fadeInUp 1s ease-out 0.8s both;
        }
    </style>
</head>
<body class="splash-container">
    <div class="splash-content">
        <div class="splash-logo">
            <img style="width: 80px;" src="twr.png" alt="">
        </div>
        <p class="splash-tagline">Speaking Hope To The World</p>
        <h1 class="splash-title">Maintenance Pro</h1>
        <p class="splash-subtitle">Management System</p>
        <p class="splash-tagline">Efficient maintenance management for modern organizations</p>
        
        
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>
        <div class="loading-text">Loading application...</div>
    </div>
    
    <div class="splash-footer">
        <p>&copy; <?php echo date('Y'); ?> Maintenance Management System. All rights reserved.</p>
    </div>

    <script>
        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add click event to skip splash screen
            document.body.addEventListener('click', function() {
                window.location.href = 'login.php';
            });
            
            // Add keyboard event to skip splash screen
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    window.location.href = 'login.php';
                }
            });
        });
    </script>
</body>
</html>