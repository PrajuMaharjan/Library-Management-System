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
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = false;
$error = '';

if ($book_id <= 0) {
    header('Location: ../index.php');
    exit();
}

$book_sql = "SELECT * FROM books WHERE id = :book_id LIMIT 1";
$book_stmt = $pdo->prepare($book_sql);
$book_stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
$book_stmt->execute();
$book = $book_stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    header('Location: ../index.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_borrow'])) {
    try {
        $pdo->beginTransaction();
        
        if ($book['available_copies'] <= 0) {
            throw new Exception('This book is currently unavailable.');
        }
        
        $update_sql = "
            UPDATE books 
            SET available_copies = available_copies - 1,
                borrow_count = borrow_count + 1
            WHERE id = :book_id AND available_copies > 0
        ";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->bindParam(':book_id', $book_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        // Check if update was successful
        if ($update_stmt->rowCount() === 0) {
            throw new Exception('Failed to borrow book. It may have just been borrowed by someone else.');
        }
        
        // Commit transaction
        $pdo->commit();
        
        $success = true;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Book - LibraryHub</title>
        <link rel="stylesheet" href="../assets/css/borrow.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="borrow-card">
            <?php if ($success): ?>
                <div class="success-container">
                    <div class="success-icon">✓</div>
                    <h1 class="success-title">Book Borrowed Successfully!</h1>
                    <p class="success-message">You have successfully borrowed this book.</p>
                    
                    <div class="book-summary">
                        <h3>Borrowing Details</h3>
                        <div class="summary-item">
                            <span class="summary-label">Book Title:</span>
                            <span class="summary-value"><?php echo htmlspecialchars($book['title']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Author:</span>
                            <span class="summary-value"><?php echo htmlspecialchars($book['author']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Borrowed Date:</span>
                            <span class="summary-value"><?php echo date('F d, Y'); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Due Date:</span>
                            <span class="due-date-highlight"><?php echo date('F d, Y', strtotime('+14 days')); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <h4>📌 Important Reminders:</h4>
                        <ul>
                            <li>Please return the book by the due date to avoid fines</li>
                            <li>Late returns are subject to a fine of $1 per day</li>
                            <li>Take good care of the book - damage fees may apply</li>
                        </ul>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="../index.php" class="btn btn-back">← Back to Browse</a>
                    </div>
                </div>
                
            <?php elseif (!empty($error)): ?>
                <!-- Error State -->
                <div class="error-container">
                    <div class="error-icon">✗</div>
                    <h1 class="error-title">Unable to Borrow Book</h1>
                    <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
                    
                    <div class="action-buttons">
                        <a href="book-details.php?id=<?php echo $book_id; ?>" class="btn btn-secondary">View Book Details</a>
                        <a href="../index.php" class="btn btn-back">← Back to Browse</a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Confirmation State -->
                <div class="confirm-container">
                    <div class="confirm-icon">📚</div>
                    <h1 class="confirm-title">Confirm Book Borrowing</h1>
                    <p class="confirm-message">Are you sure you want to borrow this book?</p>
                    
                    <div class="book-summary">
                        <h3>Book Information</h3>
                        <div class="summary-item">
                            <span class="summary-label">Title:</span>
                            <span class="summary-value"><?php echo htmlspecialchars($book['title']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Author:</span>
                            <span class="summary-value"><?php echo htmlspecialchars($book['author']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Genre:</span>
                            <span class="summary-value"><?php echo htmlspecialchars($book['genre']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Available Copies:</span>
                            <span class="summary-value"><?php echo $book['available_copies']; ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Borrowing Period:</span>
                            <span class="summary-value">14 days</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Due Date:</span>
                            <span class="due-date-highlight"><?php echo date('F d, Y', strtotime('+14 days')); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <h4> Borrowing Terms:</h4>
                        <ul>
                            <li>You must return the book within 14 days</li>
                            <li>Late returns incur a fine of $1 per day</li>
                            <li>You are responsible for any damage to the book</li>
                            <li>Lost books must be paid for in full</li>
                        </ul>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="action-buttons">
                            <button type="submit" name="confirm_borrow" class="btn btn-primary">
                                ✓ Confirm Borrow
                            </button>
                            <a href="../index.php" class="btn btn-secondary">
                                ✗ Cancel
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
