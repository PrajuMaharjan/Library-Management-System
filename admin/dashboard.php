<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

require("../config/db.php");

/**
 * Return cover image src
 */
function bookCoverSrc($book) {
    if (!empty($book['cover_image'])) {
        return 'data:image/jpeg;base64,' . base64_encode($book['cover_image']);
    }
    return 'https://via.placeholder.com/300x450/667eea/ffffff?text=No+Cover';
}

// Handle success/error messages
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $success_message = 'Book added successfully!';
            break;
        case 'updated':
            $success_message = 'Book updated successfully!';
            break;
        case 'deleted':
            $success_message = 'Book deleted successfully!';
            break;
    }
}

if (isset($_GET['error'])) {
    $error_message = 'An error occurred. Please try again.';
}

/**
 * Fetch books with covers
 */
$sql = "
    SELECT 
        b.*,
        bc.image AS cover_image
    FROM books b
    LEFT JOIN book_covers bc 
        ON bc.book_name = b.title 
";
$stmt = $pdo->query($sql);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total book count
$total_books = count($books);

// Get total users count
$users_stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$total_users = $users_stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Library Management System</title>
    <link rel ="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="admin-header">
        <h1> Admin Dashboard</h1>
        <div class="header-info">
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>!</span>
            <div>
                <a href="../index.php" class="logout-btn" style="margin-right: 10px;">View Site</a>
                <a href="../authentication/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    
    <!-- Stats Section -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-label">Total Books</div>
            <div class="stat-number"><?php echo $total_books; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-number"><?php echo $total_users; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Available Books</div>
            <div class="stat-number">
                <?php 
                $available = array_filter($books, function($book) {
                    return $book['available_copies'] > 0;
                });
                echo count($available);
                ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Borrowed Books</div>
            <div class="stat-number">
                <?php 
                $borrowed = array_sum(array_column($books, 'borrow_count'));
                echo $borrowed;
                ?>
            </div>
        </div>
    </div>
    
    <div class="container">
        
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                ✓ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                ✗ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Actions Bar -->
        <div class="actions-bar">
            <h2>📚 Library Books Management</h2>
            <a href="create.php" class="add-btn">
                <span>➕</span>
                <span>Add New Book</span>
            </a>
        </div>
        
        <!-- Books Grid -->
        <?php if (empty($books)): ?>
            <div class="empty-state">
                <h3>No books in the library yet</h3>
                <p>Click the "Add New Book" button above to get started!</p>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($books as $book): ?>
                    <div class="book">
                        <img 
                            src="<?php echo bookCoverSrc($book); ?>"
                            alt="<?php echo htmlspecialchars($book['title']); ?>"
                            loading="lazy"
                        >
                        <div class="title">
                            <?php echo htmlspecialchars($book['title']); ?>
                        </div>
                        <div class="author">
                            by <?php echo htmlspecialchars($book['author'] ?? 'Unknown'); ?>
                        </div>
                        <?php if (!empty($book['genre'])): ?>
                            <span class="genre"><?php echo htmlspecialchars($book['genre']); ?></span>
                        <?php endif; ?>
                        <div class="availability">
                            <span class="<?php echo ($book['available_copies'] > 0) ? 'available' : 'unavailable'; ?>">
                                <?php echo ($book['available_copies'] > 0) ? '✓ Available (' . $book['available_copies'] . ')' : '✗ Unavailable'; ?>
                            </span>
                        </div>
                        <div class="book-actions">
                            <a href="edit.php?id=<?php echo $book['id']; ?>" class="btn btn-edit">
                                 Edit
                            </a>
                            <a href="delete.php?id=<?php echo $book['id']; ?>" 
                               class="btn btn-delete"
                               onclick="return confirm('Are you sure you want to delete \'<?php echo htmlspecialchars(addslashes($book['title'])); ?>\'?');">
                                 Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>