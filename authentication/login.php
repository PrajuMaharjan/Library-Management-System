    <?php
    session_start();

    // Include database configuration
    require_once '../config/db.php';

    $error = '';
    $success = '';

    // Handle login form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username_input = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password_input = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($username_input)) {
            $error = "Please enter your username.";
        } elseif (empty($password_input)) {
            $error = "Please enter your password.";
        } else {
            try {
                // Only check username now
                $stmt = $pdo->prepare("SELECT * FROM users WHERE name = :username LIMIT 1");
                $stmt->bindParam(':username', $username_input, PDO::PARAM_STR);
                $stmt->execute();

                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    if (password_verify($password_input, $user['password'])) {
                        if (($user['status'] ?? 'active') === 'active') {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['name'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['role'] = $user['role'];

                            // Redirect based on role
                            if ($user['role'] === 'admin') {
                                header('Location: ../admin/dashboard.php');
                            } else {
                                header('Location: ../index.php');
                            }
                            exit();
                        } else {
                            $error = "Your account is " . htmlspecialchars($user['status']) . ". Please contact support.";
                        }
                    } else {
                        $error = "Invalid username or password.";
                    }
                } else {
                    $error = "Invalid username or password.";
                }
            } catch (PDOException $e) {
                $error = "An error occurred. Please try again later.";
                $consoleError = "Login error: " . $e->getMessage();
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LibraryHub</title>
    <style>
    body { font-family: Arial, sans-serif; background:#f4f6f8; margin:0; padding:20px;}
    .login-container { max-width:400px; margin:50px auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 4px 10px rgba(0,0,0,.1);}
    .login-box h1 { text-align:center; }
    .form-group { margin-bottom:15px; }
    input { width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; }
    button { width:100%; padding:10px; border:none; background:#3498db; color:#fff; font-size:16px; border-radius:4px; cursor:pointer; }
    button:hover { background:#2980b9; }
    .alert { padding:10px; margin-bottom:15px; border-radius:4px; }
    .alert-error { background:#f8d7da; color:#721c24; }
    .alert-success { background:#d4edda; color:#155724; }
    .form-footer { margin-top:15px; text-align:center; }
    </style>
    </head>
    <body>
    <div class="login-container">
        <div class="login-box">
            <h1>Welcome Back</h1>
            <p>Login to your Library account</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <script>
                    console.error(<?php echo json_encode($error); ?>);
                    <?php if (isset($consoleError)) { ?>
                        console.error(<?php echo json_encode($consoleError); ?>);
                    <?php } ?>
                </script>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Login</button>
            </form>

            <div class="form-footer">
                <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
                <p><a href="../index.php">← Back to Home</a></p>
            </div>
        </div>
    </div>

    <script>
    // Client-side validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;

        if (username === '') {
            alert('Please enter your username.');
            e.preventDefault();
            return false;
        }
        if (password === '') {
            alert('Please enter your password.');
            e.preventDefault();
            return false;
        }
    });
    </script>
    </body>
    </html>