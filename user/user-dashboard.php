<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/login.php");
    exit();
}

// Check if user is a regular user (not admin)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle book return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_book'])) {
    $book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
    
    if ($book_id > 0) {
        try {
            $pdo->beginTransaction();
            
            // Update book availability (increase available copies, decrease borrow count)
            $update_sql = "
                UPDATE books 
                SET available_copies = available_copies + 1,
                    borrow_count = GREATEST(borrow_count - 1, 0)
                WHERE id = :book_id
            ";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
            $update_stmt->execute();
            
            if ($update_stmt->rowCount() > 0) {
                $pdo->commit();
                $success_message = "Book returned successfully!";
            } else {
                throw new Exception("Failed to return book.");
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = $e->getMessage();
        }
    }
}

// Fetch all books where borrow_count > 0 (these are borrowed books)
// Since we don't track individual borrowings, we show all books that have been borrowed
$sql = "
    SELECT 
        b.*,
        bc.image AS cover_image
    FROM books b
    LEFT JOIN book_covers bc 
        ON bc.book_name = b.title
    WHERE b.borrow_count > 0
    ORDER BY b.title ASC
";
$stmt = $pdo->query($sql);
$borrowed_books = $stmt->fetchAll(PDO::FETCH_ASSOC);

function bookCoverSrc($book) {
    if (!empty($book['cover_image'])) {
        return 'data:image/jpeg;base64,' . base64_encode($book['cover_image']);
    }
    return 'https://via.placeholder.com/300x450/667eea/ffffff?text=No+Cover';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Borrowed Books - LibraryHub</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .dashboard-header h1 {
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .dashboard-header p {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
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
        
        .books-section {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .section-title {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid #f0f0f0;
        }
        
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .book-card {
            background: #f8f9fa;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .book-image {
            height: 350px;
            overflow: hidden;
            position: relative;
        }
        
        .book-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .borrowed-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #e74c3c;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .book-details {
            padding: 20px;
        }
        
        .book-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .book-author {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 12px;
        }
        
        .book-info {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #555;
        }
        
        .book-genre {
            background: #e8f4f8;
            color: #667eea;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .borrow-count {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .return-form {
            margin-top: 15px;
        }
        
        .btn-return {
            width: 100%;
            padding: 12px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-return:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }
        
        .empty-icon {
            font-size: 80px;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
        .empty-title {
            font-size: 24px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .empty-message {
            font-size: 16px;
            color: #95a5a6;
            margin-bottom: 30px;
        }
        
        .btn-browse {
            display: inline-block;
            padding: 15px 40px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-browse:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .stats-bar {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .stat-card {
            flex: 1;
            min-width: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .dashboard-header h1 {
                font-size: 28px;
            }
            
            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
            }
            
            .stats-bar {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="dashboard-header">
            <h1>📚 My Borrowed Books</h1>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        </div>
        
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
        
        <div class="books-section">
            <?php if (!empty($borrowed_books)): ?>
                <div class="stats-bar">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($borrowed_books); ?></div>
                        <div class="stat-label">Books Currently Borrowed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo array_sum(array_column($borrowed_books, 'borrow_count')); ?></div>
                        <div class="stat-label">Total Borrow Count</div>
                    </div>
                </div>
                
                <h2 class="section-title">Currently Borrowed Books</h2>
                
                <div class="books-grid">
                    <?php foreach ($borrowed_books as $book): ?>
                        <div class="book-card">
                            <div class="book-image">
                                <img src="<?php echo bookCoverSrc($book); ?>" 
                                     alt="<?php echo htmlspecialchars($book['title']); ?>">
                                <span class="borrowed-badge">Borrowed</span>
                            </div>
                            <div class="book-details">
                                <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="book-author">by <?php echo htmlspecialchars($book['author']); ?></p>
                                
                                <?php if (!empty($book['genre'])): ?>
                                    <span class="book-genre"><?php echo htmlspecialchars($book['genre']); ?></span>
                                <?php endif; ?>
                                
                                <div class="book-info">
                                    <span class="borrow-count">📖 Borrowed: <?php echo $book['borrow_count']; ?> times</span>
                                </div>
                                
                                <form method="POST" class="return-form" onsubmit="return confirm('Are you sure you want to return this book?');">
                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                    <button type="submit" name="return_book" class="btn-return">
                                        ✓ Return Book
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📚</div>
                    <h2 class="empty-title">No Borrowed Books</h2>
                    <p class="empty-message">You haven't borrowed any books yet. Start exploring our collection!</p>
                    <a href="../index.php" class="btn-browse">Browse Books</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
