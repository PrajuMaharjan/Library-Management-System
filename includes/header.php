<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && isset($_SESSION['username']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.header {
    background: midnightblue;
    color: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.header-main {
    padding: 20px 0;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

nav {
    display: flex;
    gap: 30px;
    align-items: center;
}

nav a,a.btn{
    color: white;
    text-decoration: none;
    padding: 8px 15px;
    border-radius: 5px;
    font-weight: 500;
    transition: all 0.3s ease;
}

nav a:hover,a.btn:hover{
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
}

nav a.active,a.btn.active{
    background: rgba(255,255,255,0.3);
}

.user-section {
    display: flex;
    align-items: center;
    gap: 15px;
}

.auth-links {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-logout {
    color: white;
    text-decoration: none;
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.95em;
    transition: all 0.3s ease;
    background: rgba(231, 76, 60, 0.8);
    border: 2px solid rgba(231, 76, 60, 0.3);
}

.btn-logout:hover {
    background: rgba(231, 76, 60, 1);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
    </style>
</head>
<body>
    <header class="header">        
        <div class="header-main">
            <div class="container">
                <div class="logo-section">
                    <?php if ($is_logged_in && isset($_SESSION['name']) && !empty($_SESSION['name'])): ?>
                        <span style="color:white; font-weight:bold; font-size: 40px;">
                            Welcome <?php echo htmlspecialchars($_SESSION['name']); ?>
                        </span>
                    <?php else: ?>
                        <span style="color:white; font-weight:bold; font-size: 40px;">
                            Welcome to the Library
                        </span>
                    <?php endif; ?>
                </div>
                
                <nav>
                    <a href="#featured">Featured Books</a>
                    <a href="#books">Catalog</a>
                    
                    <?php if ($is_logged_in): ?>
                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
                            <a href="../admin/dashboard.php">Admin Dashboard</a>
                        <?php else: ?>
                            <a href="user/user-dashboard.php">My Dashboard</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </nav>
                
                <div class="user-section">
                    <?php if ($is_logged_in): ?>
                        <span style="color: white; margin-right: 15px;">
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
                                <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 3px; font-size: 12px; margin-left: 5px;">Admin</span>
                            <?php endif; ?>
                        </span>
                        <a href="../authentication/logout.php" class="btn">Logout</a>
                    <?php else: ?>
                        <div class="auth-links">
                            <a href="authentication/login.php" class="btn">Login</a>
                            <a href="authentication/signup.php" class="btn">Sign Up</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
</body>
</html>