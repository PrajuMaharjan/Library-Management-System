<?php
session_start();
require_once '../config/db.php';

// Helper function for book covers
function bookCoverSrc($book) {
    if (!empty($book['cover_image'])) {
        return 'data:image/jpeg;base64,' . base64_encode($book['cover_image']);
    }
    return 'https://via.placeholder.com/400x600/667eea/ffffff?text=No+Cover';
}

// Check if user is logged in and get their role
$is_logged_in = isset($_SESSION['user_id']);
$user_type = $is_logged_in ? $_SESSION['role'] : null;
$is_admin = ($user_type === 'admin');
$is_user = ($user_type === 'user');

// Get book ID from URL
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($book_id <= 0) {
    header('Location: index.php');
    exit();
}

// Fetch book details with cover
$sql = "
    SELECT 
        b.*,
        bc.image AS cover_image
    FROM books b
    LEFT JOIN book_covers bc 
        ON bc.book_name = b.title
    WHERE b.id = :book_id
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
$stmt->execute();
$book = $stmt->fetch(PDO::FETCH_ASSOC);

// If book not found, redirect
if (!$book) {
    header('Location: index.php');
    exit();
}

// Get similar books (same genre, exclude current book)
$similar_sql = "
    SELECT 
        b.*,
        bc.image AS cover_image
    FROM books b
    LEFT JOIN book_covers bc 
        ON bc.book_name = b.title
    WHERE b.genre = :genre 
    AND b.id != :book_id
    ORDER BY RAND()
    LIMIT 3
";

$similar_stmt = $pdo->prepare($similar_sql);
$similar_stmt->bindParam(':genre', $book['genre'], PDO::PARAM_STR);
$similar_stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
$similar_stmt->execute();
$similar_books = $similar_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - LibraryHub</title>
    <link rel="stylesheet" href="../assets/css/book_details.css">
        
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <a href="../index.php" class="back-link">← Back to Browse</a>
        
        <div class="book-detail-container">
            <div class="book-detail-grid">
                <!-- Book Cover -->
                <div class="book-cover-section">
                    <img src="<?php echo bookCoverSrc($book); ?>" 
                         alt="<?php echo htmlspecialchars($book['title']); ?>" 
                         class="book-cover-large">
                    <div class="availability-badge <?php echo ($book['available_copies'] > 0) ? 'available' : 'unavailable'; ?>">
                        <?php echo ($book['available_copies'] > 0) ? '✓ Available' : '✗ Unavailable'; ?>
                    </div>
                </div>
                
                <!-- Book Information -->
                <div class="book-info-section">
                    <h1><?php echo htmlspecialchars($book['title']); ?></h1>
                    <p class="book-author">by <?php echo htmlspecialchars($book['author']); ?></p>
                    
                    <div class="book-meta-grid">
                        <div class="meta-item">
                            <span class="meta-label">Rating</span>
                            <span class="meta-value rating-large">
                                ⭐ <?php echo number_format($book['rating'], 1); ?>/5
                            </span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Genre</span>
                            <span class="meta-value">
                                <span class="genre-badge"><?php echo htmlspecialchars($book['genre']); ?></span>
                            </span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Publisher</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book['publisher'] ?? 'N/A'); ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Publication Year</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book['publication_year'] ?? 'N/A'); ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">ISBN</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book['isbn'] ?? 'N/A'); ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Times Borrowed</span>
                            <span class="meta-value">📖 <?php echo $book['borrow_count']; ?> times</span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Total Copies</span>
                            <span class="meta-value"><?php echo $book['total_copies']; ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Available Copies</span>
                            <span class="meta-value"><?php echo $book['available_copies']; ?></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($book['description'])): ?>
                    <div class="description-section">
                        <h3>Description</h3>
                        <p class="description-text"><?php echo nl2br(htmlspecialchars($book['description'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="action-buttons">
                        <?php if ($is_user && $book['available_copies'] > 0): ?>
                            <a href="borrow.php?id=<?php echo $book['id']; ?>" class="btn btn-primary">
                                 Borrow This Book
                            </a>
                        <?php elseif (!$is_logged_in && $book['available_copies'] > 0): ?>
                            <a href="authentication/login.php?redirect=borrow.php?id=<?php echo $book['id']; ?>" class="btn btn-primary">
                             Login to Borrow
                            </a>
                        <?php elseif ($book['available_copies'] <= 0): ?>
                            <button class="btn btn-disabled" disabled>
                                ✗ Currently Unavailable
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($is_admin): ?>
                            <a href="admin/edit.php?id=<?php echo $book['id']; ?>" class="btn btn-edit">
                                 Edit Book
                            </a>
                            <a href="admin/delete.php?id=<?php echo $book['id']; ?>" 
                               class="btn btn-delete"
                               onclick="return confirm('Are you sure you want to delete this book?')">
                                 Delete Book
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Similar Books -->
        <?php if (!empty($similar_books)): ?>
        <div class="similar-books-section">
            <h2> Similar Books You Might Like</h2>
            <div class="similar-books-grid">
                <?php foreach ($similar_books as $similar): ?>
                <div class="similar-book-card" onclick="window.location.href='book-details.php?id=<?php echo $similar['id']; ?>'">
                    <img src="<?php echo bookCoverSrc($similar); ?>" 
                         alt="<?php echo htmlspecialchars($similar['title']); ?>" 
                         class="similar-book-image">
                    <div class="similar-book-info">
                        <div class="similar-book-title"><?php echo htmlspecialchars($similar['title']); ?></div>
                        <div class="similar-book-author">by <?php echo htmlspecialchars($similar['author']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>