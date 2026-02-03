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
            $delete_stmt->execute([':id'=>$book_id]);
            
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
    <link rel ="stylesheet" href="../assets/css/delete.css">
</head>
<body>
    <!-- Admin Header -->
    <div class="admin-header">
        <h1> Delete Book</h1>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <div class="delete-card">
            <!-- Warning Header -->
            <div class="warning-header">
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
                    <h3 class="section-title"> Step 1: Specify Number of Copies</h3>
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
                    <h3 class="section-title"> Step 2: Confirm Deletion</h3>
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
                         Delete Copies
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
                    deleteInfoText.innerHTML = ' <strong>Warning:</strong> You are deleting all copies. This will completely remove the book "<?php echo htmlspecialchars(addslashes($book['title'])); ?>" from the library system, including its cover image.';
                } else {
                    const newTotal = totalCopies - copiesToDelete;
                    const newAvailable = Math.max(0, availableCopies - copiesToDelete);
                    deleteInfoText.innerHTML = ` <strong>Result:</strong> After deletion, there will be ${newTotal} total copies (${newAvailable} available) remaining.`;
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