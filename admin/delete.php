<?php
session_start();


require_once("../config/db.php");

$error_message = '';
$book = null;

// Get book ID from URL
$book_id = $_GET['id'] ?? null;

if (!$book_id) {
    header('Location: dashboard.php?error=no_id');
    exit();
}

// Fetch book details
try {
    $sql = "
        SELECT 
            b.*,
            bc.image AS cover_image
        FROM books b
        LEFT JOIN book_covers bc 
            ON bc.book_name = b.title
        WHERE b.id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $book_id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        header('Location: dashboard.php?error=not_found');
        exit();
    }
} catch (Exception $e) {
    header('Location: dashboard.php?error=fetch_failed');
    exit();
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $copies_to_delete = intval($_POST['copies_to_delete'] ?? 0);
        $confirm = $_POST['confirm'] ?? '';
        
        // Validation
        if ($copies_to_delete <= 0) {
            throw new Exception('Please specify a valid number of copies to delete (greater than 0)');
        }
        
        if ($confirm !== 'yes') {
            throw new Exception('Please confirm the deletion');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if deleting all copies
        if ($copies_to_delete >= $book['total_copies']) {
            // Delete entire book record
            $delete_sql = "DELETE FROM books WHERE id = :id";
            $delete_stmt = $pdo->prepare($delete_sql);
            $delete_stmt->execute([':id' => $book_id]);
            
            // Also delete the cover image
            $delete_cover_sql = "DELETE FROM book_covers WHERE book_name = :book_name";
            $delete_cover_stmt = $pdo->prepare($delete_cover_sql);
            $delete_cover_stmt->execute([':book_name' => $book['title']]);
            
            $pdo->commit();
            
            // Redirect with success message
            header('Location: dashboard.php?success=deleted');
            exit();
        } else {
            // Subtract copies from total and available
            $new_total_copies = $book['total_copies'] - $copies_to_delete;
            $new_available_copies = max(0, $book['available_copies'] - $copies_to_delete);
            
            // Update book record
            $update_sql = "UPDATE books SET 
                            total_copies = :total_copies,
                            available_copies = :available_copies
                          WHERE id = :id";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                ':total_copies' => $new_total_copies,
                ':available_copies' => $new_available_copies,
                ':id' => $book_id
            ]);
            
            $pdo->commit();
            
            // Redirect with success message
            header('Location: dashboard.php?success=updated');
            exit();
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = $e->getMessage();
    }
}

// Function to get cover image src
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
    <title>Delete Book - Library Admin</title>
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
            margin-bottom: 5px;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            opacity: 0.9;
            transition: opacity 0.3s;
        }
        
        .back-link:hover {
            opacity: 1;
        }
        
        /* Container */
        .container {
            max-width: 700px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Delete Card */
        .delete-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .warning-header {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .warning-header h2 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .warning-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .warning-text {
            color: #856404;
            font-size: 14px;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Book Info Section */
        .book-info {
            display: flex;
            gap: 25px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .book-cover {
            flex-shrink: 0;
        }
        
        .book-cover img {
            width: 150px;
            height: 225px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .book-details {
            flex: 1;
        }
        
        .book-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .book-author {
            font-size: 16px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .book-stat {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .book-stat:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            font-weight: bold;
            color: #495057;
        }
        
        .stat-value {
            color: #6c757d;
        }
        
        /* Form Section */
        .delete-form {
            margin-top: 30px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding: 25px;
            background: #fff9e6;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            font-family: Arial, sans-serif;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        input[type="number"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .helper-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .delete-info {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        
        .delete-info-text {
            color: #721c24;
            font-size: 14px;
            margin: 0;
        }
        
        /* Confirmation Section */
        .confirmation-section {
            background: #ffe6e6;
            border: 2px solid #e74c3c;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-label {
            color: #c0392b;
            font-weight: bold;
            cursor: pointer;
            user-select: none;
        }
        
        /* Buttons */
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
            flex: 1;
        }
        
        .btn-danger:hover:not(:disabled) {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        .btn-danger:disabled {
            background: #cccccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .admin-header {
                padding: 15px 20px;
            }
            
            .container {
                margin: 20px auto;
            }
            
            .delete-card {
                padding: 25px 20px;
            }
            
            .book-info {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Header -->
    <div class="admin-header">
        <h1>🗑️ Delete Book</h1>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <div class="delete-card">
            <!-- Warning Header -->
            <div class="warning-header">
                <div class="warning-icon">⚠️</div>
                <h2>Delete Book Copies</h2>
                <p class="warning-text">Please specify how many copies you want to delete</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    ✗ <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Book Information -->
            <div class="book-info">
                <div class="book-cover">
                    <img src="<?php echo bookCoverSrc($book); ?>" alt="Book Cover">
                </div>
                <div class="book-details">
                    <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                    <div class="book-author">by <?php echo htmlspecialchars($book['author']); ?></div>
                    
                    <div class="book-stat">
                        <span class="stat-label">Total Copies:</span>
                        <span class="stat-value"><?php echo $book['total_copies']; ?></span>
                    </div>
                    <div class="book-stat">
                        <span class="stat-label">Available Copies:</span>
                        <span class="stat-value"><?php echo $book['available_copies']; ?></span>
                    </div>
                    <div class="book-stat">
                        <span class="stat-label">Currently Borrowed:</span>
                        <span class="stat-value"><?php echo $book['total_copies'] - $book['available_copies']; ?></span>
                    </div>
                    <div class="book-stat">
                        <span class="stat-label">Times Borrowed:</span>
                        <span class="stat-value"><?php echo $book['borrow_count']; ?> times</span>
                    </div>
                </div>
            </div>
            
            <!-- Delete Form -->
            <form method="POST" id="deleteForm" class="delete-form">
                <!-- Copies to Delete -->
                <div class="form-section">
                    <h3 class="section-title">📊 Step 1: Specify Number of Copies</h3>
                    <div class="form-group">
                        <label for="copies_to_delete">
                            How many copies do you want to delete? <span class="required">*</span>
                        </label>
                        <input type="number" 
                               id="copies_to_delete" 
                               name="copies_to_delete" 
                               min="1" 
                               max="<?php echo $book['total_copies']; ?>"
                               required
                               onchange="updateDeleteInfo()">
                        <div class="helper-text">
                            Enter a number between 1 and <?php echo $book['total_copies']; ?>
                        </div>
                    </div>
                    
                    <div id="deleteInfo" class="delete-info" style="display: none;">
                        <p class="delete-info-text" id="deleteInfoText"></p>
                    </div>
                </div>
                
                <!-- Confirmation -->
                <div class="confirmation-section">
                    <h3 class="section-title">✅ Step 2: Confirm Deletion</h3>
                    <div class="checkbox-group">
                        <input type="checkbox" 
                               id="confirmCheckbox" 
                               name="confirm" 
                               value="yes"
                               onchange="toggleSubmitButton()">
                        <label for="confirmCheckbox" class="checkbox-label">
                            Yes, I understand this action and want to proceed
                        </label>
                    </div>
                </div>
                
                <!-- Submit Buttons -->
                <div class="btn-group">
                    <button type="submit" id="submitBtn" class="btn btn-danger" disabled>
                        🗑️ Delete Copies
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function updateDeleteInfo() {
            const copiesToDelete = parseInt(document.getElementById('copies_to_delete').value) || 0;
            const totalCopies = <?php echo $book['total_copies']; ?>;
            const availableCopies = <?php echo $book['available_copies']; ?>;
            const deleteInfo = document.getElementById('deleteInfo');
            const deleteInfoText = document.getElementById('deleteInfoText');
            
            if (copiesToDelete > 0) {
                deleteInfo.style.display = 'block';
                
                if (copiesToDelete >= totalCopies) {
                    deleteInfoText.innerHTML = '⚠️ <strong>Warning:</strong> You are deleting all copies. This will completely remove the book "<?php echo htmlspecialchars(addslashes($book['title'])); ?>" from the library system, including its cover image.';
                } else {
                    const newTotal = totalCopies - copiesToDelete;
                    const newAvailable = Math.max(0, availableCopies - copiesToDelete);
                    deleteInfoText.innerHTML = `ℹ️ <strong>Result:</strong> After deletion, there will be ${newTotal} total copies (${newAvailable} available) remaining.`;
                }
            } else {
                deleteInfo.style.display = 'none';
            }
            
            // Reset confirmation checkbox
            document.getElementById('confirmCheckbox').checked = false;
            toggleSubmitButton();
        }
        
        function toggleSubmitButton() {
            const confirmCheckbox = document.getElementById('confirmCheckbox');
            const submitBtn = document.getElementById('submitBtn');
            const copiesToDelete = parseInt(document.getElementById('copies_to_delete').value) || 0;
            
            submitBtn.disabled = !(confirmCheckbox.checked && copiesToDelete > 0);
        }
        
        // Form validation before submit
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            const copiesToDelete = parseInt(document.getElementById('copies_to_delete').value) || 0;
            const totalCopies = <?php echo $book['total_copies']; ?>;
            const confirmCheckbox = document.getElementById('confirmCheckbox');
            
            if (copiesToDelete <= 0) {
                e.preventDefault();
                alert('Please enter a valid number of copies to delete.');
                return;
            }
            
            if (copiesToDelete > totalCopies) {
                e.preventDefault();
                alert(`You cannot delete more than ${totalCopies} copies!`);
                return;
            }
            
            if (!confirmCheckbox.checked) {
                e.preventDefault();
                alert('Please confirm the deletion by checking the checkbox.');
                return;
            }
            
            // Final confirmation
            let confirmMessage;
            if (copiesToDelete >= totalCopies) {
                confirmMessage = `Are you absolutely sure you want to permanently delete the book "${<?php echo json_encode($book['title']); ?>}" and all its data from the library?\n\nThis action cannot be undone!`;
            } else {
                confirmMessage = `Are you sure you want to delete ${copiesToDelete} cop${copiesToDelete > 1 ? 'ies' : 'y'} of "${<?php echo json_encode($book['title']); ?>}"?`;
            }
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>