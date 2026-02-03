<?php
session_start();

require_once("../config/db.php");

$error_message = '';
$success_message = '';
$book = null;

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get original title for cover update reference
        $original_title = $book['title'];
        
        // Validate required fields
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $genre = trim($_POST['genre'] ?? '');
        $publisher = trim($_POST['publisher'] ?? '');
        $publication_year = trim($_POST['publication_year'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $total_copies = intval($_POST['total_copies'] ?? 0);
        $available_copies = intval($_POST['available_copies'] ?? 0);
        
        // Validation
        if (empty($title)) {
            throw new Exception('Title is required');
        }
        if (empty($author)) {
            throw new Exception('Author is required');
        }
        if ($total_copies < 0) {
            throw new Exception('Total copies cannot be negative');
        }
        if ($available_copies < 0 || $available_copies > $total_copies) {
            throw new Exception('Available copies must be between 0 and total copies');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Update book in books table (excluding rating and borrow_count)
        $sql = "UPDATE books SET 
                    title = :title,
                    author = :author,
                    genre = :genre,
                    publisher = :publisher,
                    publication_year = :publication_year,
                    description = :description,
                    total_copies = :total_copies,
                    available_copies = :available_copies
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':author' => $author,
            ':genre' => $genre,
            ':publisher' => $publisher,
            ':publication_year' => $publication_year,
            ':description' => $description,
            ':total_copies' => $total_copies,
            ':available_copies' => $available_copies,
            ':id' => $book_id
        ]);
        
        // Handle book cover upload
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['cover_image']['tmp_name'];
            $file_type = $_FILES['cover_image']['type'];
            
            // Validate image type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (in_array($file_type, $allowed_types)) {
                // Read image file
                $image_data = file_get_contents($file_tmp);
                
                // Check if cover exists for the original book name
                $check_sql = "SELECT id FROM book_covers WHERE book_name = :book_name";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([':book_name' => $original_title]);
                
                if ($check_stmt->rowCount() > 0) {
                    // Update existing cover and change book_name if title changed
                    $cover_sql = "UPDATE book_covers SET image = :image, book_name = :new_book_name WHERE book_name = :old_book_name";
                    $cover_stmt = $pdo->prepare($cover_sql);
                    $cover_stmt->execute([
                        ':image' => $image_data,
                        ':new_book_name' => $title,
                        ':old_book_name' => $original_title
                    ]);
                } else {
                    // Insert new cover
                    $cover_sql = "INSERT INTO book_covers (book_name, image) VALUES (:book_name, :image)";
                    $cover_stmt = $pdo->prepare($cover_sql);
                    $cover_stmt->execute([
                        ':book_name' => $title,
                        ':image' => $image_data
                    ]);
                }
            }
        } elseif ($title !== $original_title) {
            // If title changed but no new image uploaded, update book_name in book_covers
            $update_cover_name = "UPDATE book_covers SET book_name = :new_name WHERE book_name = :old_name";
            $update_stmt = $pdo->prepare($update_cover_name);
            $update_stmt->execute([
                ':new_name' => $title,
                ':old_name' => $original_title
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect to admin dashboard with success message
        header('Location: dashboard.php?success=updated');
        exit();
        
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
    <title>Edit Book - Library Admin</title>
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
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* Form Card */
        .form-card {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .form-card h2 {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
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
        
        /* Current Cover Section */
        .current-cover {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .current-cover h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
        }
        
        .current-cover img {
            max-width: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* Read-only info */
        .info-section {
            background: #fff9e6;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #ffc107;
        }
        
        .info-section h3 {
            color: #856404;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #fff3cd;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: bold;
            color: #333;
        }
        
        .info-value {
            color: #666;
        }
        
        /* Form Groups */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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
        
        input[type="text"],
        input[type="number"],
        input[type="file"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: Arial, sans-serif;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="number"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        input[type="file"] {
            padding: 10px;
            cursor: pointer;
        }
        
        .file-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Buttons */
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
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
        
        .btn-primary {
            background: #3498db;
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        /* Preview */
        .cover-preview {
            margin-top: 15px;
            max-width: 200px;
        }
        
        .cover-preview img {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .admin-header {
                padding: 15px 20px;
            }
            
            .container {
                margin: 20px auto;
            }
            
            .form-card {
                padding: 25px 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
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
        <h1> Edit Book</h1>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
    
    <div class="container">
        <div class="form-card">
            <h2>Edit Book Information</h2>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    ✗ <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Current Cover -->
            <div class="current-cover">
                <h3> Current Book Cover</h3>
                <img src="<?php echo bookCoverSrc($book); ?>" alt="Current Cover">
            </div>
            
            <!-- Read-only Information (Rating and Borrow Count) -->
            <div class="info-section">
                <h3> User-Generated Statistics (Read-Only)</h3>
                <div class="info-item">
                    <span class="info-label">Current Rating:</span>
                    <span class="info-value">⭐ <?php echo number_format($book['rating'], 1); ?> / 5.0</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Times Borrowed:</span>
                    <span class="info-value"> <?php echo $book['borrow_count']; ?> times</span>
                </div>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="bookForm">
                <!-- Title and Author -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required 
                               value="<?php echo htmlspecialchars($book['title']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="author">Author <span class="required">*</span></label>
                        <input type="text" id="author" name="author" required 
                               value="<?php echo htmlspecialchars($book['author']); ?>">
                    </div>
                </div>
                
                <!-- Genre and Publisher -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="genre">Genre</label>
                        <input type="text" id="genre" name="genre" 
                               value="<?php echo htmlspecialchars($book['genre']); ?>"
                               placeholder="e.g., Fiction, Non-Fiction, Science">
                    </div>
                    
                    <div class="form-group">
                        <label for="publisher">Publisher</label>
                        <input type="text" id="publisher" name="publisher" 
                               value="<?php echo htmlspecialchars($book['publisher']); ?>">
                    </div>
                </div>
                
                <!-- Publication Year -->
                <div class="form-group">
                    <label for="publication_year">Publication Year</label>
                    <input type="number" id="publication_year" name="publication_year" 
                           min="1000" max="<?php echo date('Y') + 1; ?>"
                           value="<?php echo htmlspecialchars($book['publication_year']); ?>">
                </div>
                
                <!-- Total Copies and Available Copies -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="total_copies">Total Copies <span class="required">*</span></label>
                        <input type="number" id="total_copies" name="total_copies" 
                               min="0" required
                               value="<?php echo htmlspecialchars($book['total_copies']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="available_copies">Available Copies <span class="required">*</span></label>
                        <input type="number" id="available_copies" name="available_copies" 
                               min="0" required
                               value="<?php echo htmlspecialchars($book['available_copies']); ?>">
                    </div>
                </div>
                
                <!-- Description -->
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Enter a brief description of the book..."><?php echo htmlspecialchars($book['description']); ?></textarea>
                </div>
                
                <!-- Book Cover -->
                <div class="form-group">
                    <label for="cover_image">Update Book Cover Image (Optional)</label>
                    <input type="file" id="cover_image" name="cover_image" 
                           accept="image/jpeg,image/jpg,image/png,image/gif"
                           onchange="previewCover(this)">
                    <div class="file-hint">Leave empty to keep current cover. Accepted formats: JPG, JPEG, PNG, GIF</div>
                    <div id="coverPreview" class="cover-preview" style="display: none;">
                        <img id="previewImg" src="" alt="New Cover Preview">
                    </div>
                </div>
                
                <!-- Submit Buttons -->
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        ✓ Update Book
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Preview cover image before upload
        function previewCover(input) {
            const preview = document.getElementById('coverPreview');
            const previewImg = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Validate available copies doesn't exceed total copies
        document.getElementById('bookForm').addEventListener('submit', function(e) {
            const totalCopies = parseInt(document.getElementById('total_copies').value) || 0;
            const availableCopies = parseInt(document.getElementById('available_copies').value) || 0;
            
            if (availableCopies > totalCopies) {
                e.preventDefault();
                alert('Available copies cannot exceed total copies!');
                document.getElementById('available_copies').focus();
            }
        });
        
        // Auto-update available copies when total copies changes
        document.getElementById('total_copies').addEventListener('change', function() {
            const totalCopies = parseInt(this.value) || 0;
            const availableCopiesInput = document.getElementById('available_copies');
            const currentAvailable = parseInt(availableCopiesInput.value) || 0;
            
            if (currentAvailable > totalCopies) {
                availableCopiesInput.value = totalCopies;
            }
        });
    </script>
</body>
</html>