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
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 30px;
            transition: color 0.3s;
        }
        
        .back-link:hover {
            color: #5568d3;
        }
        
        .book-detail-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 50px;
        }
        
        .book-detail-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 50px;
        }
        
        .book-cover-section {
            position: relative;
        }
        
        .book-cover-large {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }
        
        .availability-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .availability-badge.available {
            background: #27ae60;
            color: white;
        }
        
        .availability-badge.unavailable {
            background: #e74c3c;
            color: white;
        }
        
        .book-info-section h1 {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .book-author {
            font-size: 20px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        .book-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 12px;
            color: #95a5a6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .meta-value {
            font-size: 16px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .rating-large {
            color: #f39c12;
            font-size: 24px;
        }
        
        .genre-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .description-section {
            margin: 30px 0;
        }
        
        .description-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .description-text {
            color: #555;
            line-height: 1.8;
            font-size: 16px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 15px 40px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: #27ae60;
            color: white;
        }
        
        .btn-primary:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-secondary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-edit {
            background: #3498db;
            color: white;
        }
        
        .btn-edit:hover {
            background: #2980b9;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c0392b;
        }
        
        .btn-disabled {
            background: #bdc3c7;
            color: white;
            cursor: not-allowed;
        }
        
        /* Similar Books Section */
        .similar-books-section {
            margin-top: 60px;
        }
        
        .similar-books-section h2 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        
        .similar-books-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        
        .similar-book-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }
        
        .similar-book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .similar-book-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }
        
        .similar-book-info {
            padding: 20px;
        }
        
        .similar-book-title {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .similar-book-author {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .book-detail-grid {
                grid-template-columns: 1fr;
            }
            
            .book-cover-large {
                height: 400px;
            }
            
            .book-info-section h1 {
                font-size: 28px;
            }
            
            .book-meta-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .similar-books-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <a href="index.php" class="back-link">← Back to Browse</a>
        
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
                                📚 Borrow This Book
                            </a>
                        <?php elseif (!$is_logged_in && $book['available_copies'] > 0): ?>
                            <a href="authentication/login.php?redirect=borrow.php?id=<?php echo $book['id']; ?>" class="btn btn-primary">
                                🔐 Login to Borrow
                            </a>
                        <?php elseif ($book['available_copies'] <= 0): ?>
                            <button class="btn btn-disabled" disabled>
                                ✗ Currently Unavailable
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($is_admin): ?>
                            <a href="admin/edit.php?id=<?php echo $book['id']; ?>" class="btn btn-edit">
                                ✏️ Edit Book
                            </a>
                            <a href="admin/delete.php?id=<?php echo $book['id']; ?>" 
                               class="btn btn-delete"
                               onclick="return confirm('Are you sure you want to delete this book?')">
                                🗑️ Delete Book
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