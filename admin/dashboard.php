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
    <title>Admin Dashboard - LibraryHub</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            padding: 0;
        }
        
        /* Header */
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .admin-header h1 {
            margin-bottom: 10px;
        }
        
        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        
        .welcome-text {
            font-size: 14px;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* Stats Section */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        /* Main Content */
        .container {
            padding: 20px 40px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Actions Bar */
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .actions-bar h2 {
            color: #333;
        }
        
        .add-btn {
            background: #27ae60;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .add-btn:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Books Grid */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 25px;
        }
        
        .book {
            background: #fff;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,.08);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }
        
        .book:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,.12);
        }
        
        .book img {
            width: 100%;
            height: 280px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .title {
            font-weight: bold;
            margin: 10px 0 5px;
            color: #333;
            font-size: 15px;
            line-height: 1.3;
        }
        
        .author {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
        }
        
        .genre {
            display: inline-block;
            background: #e8f4f8;
            color: #667eea;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            margin: 5px 0;
        }
        
        .availability {
            font-size: 12px;
            margin: 8px 0;
        }
        
        .available {
            color: #27ae60;
            font-weight: bold;
        }
        
        .unavailable {
            color: #e74c3c;
            font-weight: bold;
        }
        
        /* Action Buttons */
        .book-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            justify-content: center;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-edit {
            background: #3498db;
            color: white;
        }
        
        .btn-edit:hover {
            background: #2980b9;
            transform: scale(1.05);
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c0392b;
            transform: scale(1.05);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #999;
        }
        
        /* Quick Links */
        .quick-links {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .quick-links h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .quick-links a {
            display: inline-block;
            margin-right: 15px;
            color: #667eea;
            text-decoration: none;
            padding: 8px 15px;
            background: #f0f3ff;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .quick-links a:hover {
            background: #e0e7ff;
        }
        
        @media (max-width: 768px) {
            .container, .stats-container {
                padding: 15px 20px;
            }
            
            .admin-header {
                padding: 15px 20px;
            }
            
            .header-info {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .actions-bar {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }
        }
    </style>
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