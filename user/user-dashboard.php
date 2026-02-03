<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/login.php");
    exit();
}

// Check if user is admin or not
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
    <title>User Dashboard</title>
    <link rel="stylesheet" href="../assets/css/user_dashboard.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="dashboard-header">
            <h1> My Borrowed Books</h1>
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
                    <h2 class="empty-title">No Borrowed Books</h2>
                    <p class="empty-message">You haven't borrowed any books yet. Start exploring our catalog!</p>
                    <a href="../index.php" class="btn-browse">Browse Books</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
