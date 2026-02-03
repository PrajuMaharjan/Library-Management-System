<?php
session_start();
require_once '../config/db.php';

$errors = [];
$debug_errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = preg_replace('/\s+/', ' ', trim($_POST['username'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($username)) $errors[] = "Username is required.";
    if (strlen($username) < 3) $errors[] = "Username must be at least 3 characters long.";
    if (!preg_match('/^[a-zA-Z0-9_ ]+$/', $username)) $errors[] = "Username can only contain letters, numbers, spaces, and underscores.";
    if (empty($email)) $errors[] = "Email is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (empty($password)) $errors[] = "Password is required.";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters long.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        try {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE name = :username LIMIT 1");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            if ($stmt->fetch()) $errors[] = "Username already taken.";

            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]); // Fixed: Pass as array
            if ($stmt->fetch()) $errors[] = "Email already registered.";
        } catch (PDOException $e) {
            $debug_errors[] = "Database error: " . $e->getMessage();
        }
    }

    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, role) 
                VALUES (:username, :email, :password, 'user')
            "); // Fixed: Removed extra comma
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);

            if (!$stmt->execute()) {
                $errors[] = "Registration failed. Please try again.";
                $debug_errors[] = "Insert failed: " . implode(" | ", $stmt->errorInfo());
            } else {
                header('Location: login.php?registered=success');
                exit();
            }
        } catch (PDOException $e) {
            $errors[] = "An error occurred during registration.";
            $debug_errors[] = "PDO Exception: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign Up - LibraryHub</title>
<link rel="stylesheet" href="../assets/css/signup.css">
</head>
<body>
<div class="signup-container">
    <div class="signup-box">
        <h1>Create Account</h1>
        <p>Join LibraryHub today</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="signup.php" id="signupForm">
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       required minlength="3" pattern="[a-zA-Z0-9_ ]+"
                       title="Letters, numbers, spaces, and underscores allowed. Minimum 3 characters.">
            </div>

            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required minlength="8">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>

            <button type="submit">Create Account</button>
        </form>

        <div class="form-footer">
            <p>Already have an account? <a href="login.php">Login here</a></p>
            <p><a href="../index.php">← Back to Home</a></p>
        </div>
    </div>
</div>

<script>
// Client-side validation
document.getElementById('signupForm').addEventListener('submit', function(e) {
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    let errors = [];
    if (username === '') errors.push('Username is required.');
    if (username.length < 3) errors.push('Username must be at least 3 characters long.');
    if (!/^[a-zA-Z0-9_ ]+$/.test(username)) errors.push('Username can only contain letters, numbers, spaces, and underscores.');
    if (email === '') errors.push('Email is required.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Invalid email format.');
    if (password === '') errors.push('Password is required.');
    if (password.length < 8) errors.push('Password must be at least 8 characters long.');
    if (password !== confirmPassword) errors.push('Passwords do not match.');

    if (errors.length > 0) {
        alert('Please fix the following errors:\n\n' + errors.join('\n'));
        e.preventDefault();
        return false;
    }
});

// Log errors to console
const userErrors = <?php echo json_encode($errors ?? []); ?>;
const debugErrors = <?php echo json_encode($debug_errors ?? []); ?>;

if (userErrors.length > 0) {
    console.group("Signup Errors");
    userErrors.forEach(e => console.error("User Error: " + e));
    console.groupEnd();
}

if (debugErrors.length > 0) {
    console.group("Debug/Internal Errors");
    debugErrors.forEach(e => console.error("Debug: " + e));
    console.groupEnd();
}
</script>
</body>
</html>