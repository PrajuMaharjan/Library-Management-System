<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location:../authentication/login.php');
    exit();
}

require_once("../config/db.php");

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $genre = trim($_POST['genre'] ?? '');
        $publisher = trim($_POST['publisher'] ?? '');
        $publication_year = trim($_POST['publication_year'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $total_copies = intval($_POST['total_copies'] ?? 0);
        $available_copies = intval($_POST['available_copies'] ?? 0);
        $rating = floatval($_POST['rating'] ?? 0);
        $borrow_count = intval($_POST['borrow_count'] ?? 0);
        
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
        if ($rating < 0 || $rating > 5) {
            throw new Exception('Rating must be between 0 and 5');
        }
        
        $sql = "INSERT INTO books (
                    title, author, genre, publisher, publication_year, 
                    description, total_copies, available_copies, rating, borrow_count
                ) VALUES (?,?,?,?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title,$author,$genre,$publisher,$publication_year,$description,$total_copies,$available_copies,$rating,$borrow_count]);
        
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['cover_image']['tmp_name'];
            $file_type = $_FILES['cover_image']['type'];
            
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (in_array($file_type,$allowed_types)) {
                $image_data=file_get_contents($file_tmp);
                
                $check_sql = "SELECT id FROM book_covers WHERE book_name = :book_name";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute(['book_name'=>$title]);
                if ($check_stmt->rowCount() > 0) {
                    $cover_sql = "UPDATE book_covers SET image = :image WHERE book_name = :book_name";
                } else {
                    $cover_sql = "INSERT INTO book_covers (book_name, image) VALUES (:book_name, :image)";
                }
                
                $cover_stmt = $pdo->prepare($cover_sql);
                $cover_stmt->execute([
                    ':book_name' => $title,
                    ':image' => $image_data
                ]);
            }
        }
        
        header('Location:dashboard.php?success=created');
        exit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Book - LibraryHub Admin</title>
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
            background: #27ae60;
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
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
    </style>
</head>
<body>
    <div class="admin-header">
        <h1> Add New Book</h1>
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
    <div class="container">
        <div class="form-card">
            <h2>Book Information</h2>
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    ✗ <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" id="bookForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required 
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="author">Author <span class="required">*</span></label>
                        <input type="text" id="author" name="author" required 
                               value="<?php echo htmlspecialchars($_POST['author'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="genre">Genre</label>
                        <input type="text" id="genre" name="genre" 
                               value="<?php echo htmlspecialchars($_POST['genre'] ?? ''); ?>"
                               placeholder="e.g., Fiction, Non-Fiction, Science">
                    </div>
                    
                    <div class="form-group">
                        <label for="publisher">Publisher</label>
                        <input type="text" id="publisher" name="publisher" 
                               value="<?php echo htmlspecialchars($_POST['publisher'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="publication_year">Publication Year</label>
                        <input type="number" id="publication_year" name="publication_year" 
                               min="1000" max="<?php echo date('Y') + 1; ?>"
                               value="<?php echo htmlspecialchars($_POST['publication_year'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="rating">Rating (0-5)</label>
                        <input type="number" id="rating" name="rating" 
                               min="0" max="5" step="0.1"
                               value="<?php echo htmlspecialchars($_POST['rating'] ?? '0'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="total_copies">Total Copies <span class="required">*</span></label>
                        <input type="number" id="total_copies" name="total_copies" 
                               min="0" required
                               value="<?php echo htmlspecialchars($_POST['total_copies'] ?? '1'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="available_copies">Available Copies <span class="required">*</span></label>
                        <input type="number" id="available_copies" name="available_copies" 
                               min="0" required
                               value="<?php echo htmlspecialchars($_POST['available_copies'] ?? '1'); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="borrow_count">Borrow Count</label>
                    <input type="number" id="borrow_count" name="borrow_count" 
                           min="0"
                           value="<?php echo htmlspecialchars($_POST['borrow_count'] ?? '0'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Enter a brief description of the book..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="cover_image">Book Cover Image</label>
                    <input type="file" id="cover_image" name="cover_image" 
                           accept="image/jpeg,image/jpg,image/png,image/gif"
                           onchange="previewCover(this)">
                    <div class="file-hint">Accepted formats: JPG, JPEG, PNG, GIF (Max 5MB)</div>
                    <div id="coverPreview" class="cover-preview" style="display: none;">
                        <img id="previewImg" src="" alt="Cover Preview">
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        ✓ Add Book
                    </button>
                    <a href="admin.php" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
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
        
        document.getElementById('bookForm').addEventListener('submit', function(e) {
            const totalCopies = parseInt(document.getElementById('total_copies').value) || 0;
            const availableCopies = parseInt(document.getElementById('available_copies').value) || 0;
            
            if (availableCopies > totalCopies) {
                e.preventDefault();
                alert('Available copies cannot exceed total copies!');
                document.getElementById('available_copies').focus();
            }
        });
        
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