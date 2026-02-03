<?php
session_start();
require_once 'config/db.php';

function bookCoverSrc($book) {
    if (!empty($book['cover_image'])) {
        return 'data:image/jpeg;base64,' . base64_encode($book['cover_image']);
    }
    return 'https://via.placeholder.com/300x450/667eea/ffffff?text=No+Cover';
}

// Check if user is logged in and get their role
$is_logged_in = isset($_SESSION['user_id']);
$user_type = $is_logged_in ? $_SESSION['role'] : null;
$is_admin = ($user_type === 'admin');
$is_user = ($user_type === 'user');

// Get filter and sort parameters
$genre = isset($_GET['genre']) ? $_GET['genre'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'title_asc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$books_per_page = 9;
$offset = ($page - 1) * $books_per_page;

// Build the SQL query for main books section with cover images
$sql = "
    SELECT 
        b.*,
        bc.image AS cover_image
    FROM books b
    LEFT JOIN book_covers bc 
        ON bc.book_name = b.title
    WHERE 1=1
";
$params = [];

if (!empty($genre)) {
    $sql .= " AND b.genre = :genre";
    $params[':genre'] = $genre;
}

// Add sorting
switch($sort) {
    case 'title_asc':
        $sql .= " ORDER BY b.title ASC";
        break;
    case 'title_desc':
        $sql .= " ORDER BY b.title DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY b.rating DESC";
        break;
    case 'borrowed':
        $sql .= " ORDER BY b.borrow_count DESC";
        break;
    default:
        $sql .= " ORDER BY b.title ASC";
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM books b WHERE 1=1";
if (!empty($genre)) {
    $count_sql .= " AND b.genre = :genre";
}
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_books = $count_stmt->fetchColumn();
$total_pages = ceil($total_books / $books_per_page);

// Get books for current page
$sql .= " LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $books_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get featured books (random 3 books) with covers
$featured_stmt = $pdo->query("
    SELECT 
        b.*,
        bc.image AS cover_image
    FROM books b
    LEFT JOIN book_covers bc 
        ON bc.book_name = b.title
    ORDER BY RAND() 
    LIMIT 3
");
$featured_books = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all genres for filter dropdown
$genres_stmt = $pdo->query("SELECT DISTINCT genre FROM books ORDER BY genre");
$genres = $genres_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LibraryHub - Home</title>
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
            padding: 0 20px;
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
            margin-bottom: 60px;
        }
        
        .hero-content h1 {
            font-size: 48px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .hero-content p {
            font-size: 20px;
            margin-bottom: 30px;
            opacity: 0.95;
        }
        
        .hero-search {
            max-width: 600px;
            margin: 0 auto 20px;
            display: flex;
            gap: 10px;
        }
        
        .hero-search input {
            flex: 1;
            padding: 15px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .hero-search button {
            padding: 15px 40px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .hero-search button:hover {
            background: #229954;
        }
        
        .login-prompt {
            margin-top: 20px;
            font-size: 16px;
        }
        
        .login-prompt a {
            color: white;
            text-decoration: underline;
            font-weight: bold;
        }
        
        /* Section Styling */
        .featured-section,
        .all-books-section {
            padding: 60px 0;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .section-title {
            font-size: 36px;
            color: #333;
            margin-bottom: 50px;
            padding-bottom: 20px;
            border-bottom: 3px solid #f0f0f0;
            font-weight: 700;
        }
        
        .text-center {
            text-align: center;
        }
        
        /* Featured Books - 3 columns */
        .featured-books {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .featured-book-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .featured-book-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .book-image {
            position: relative;
            height: 400px;
            overflow: hidden;
        }
        
        .book-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .book-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .featured-book-card:hover .book-overlay {
            opacity: 1;
        }
        
        .btn-view {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .btn-view:hover {
            background: #5568d3;
        }
        
        .book-info {
            padding: 20px;
        }
        
        .book-info h3 {
            font-size: 20px;
            margin-bottom: 8px;
            color: #333;
        }
        
        .author {
            color: #666;
            font-size: 14px;
            margin-bottom: 12px;
        }
        
        .book-meta {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .rating {
            color: #f39c12;
            font-weight: bold;
        }
        
        .genre {
            background: #e8f4f8;
            color: #667eea;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
        }
        
        /* Admin Controls */
        .admin-controls {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin: 40px 0 50px 0;
            flex-wrap: wrap;
        }
        
        .btn-admin {
            padding: 12px 25px;
            background: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-admin:hover {
            background: #c0392b;
        }
        
        /* Search Container */
        .search-container {
            max-width: 600px;
            margin: 0 auto 40px;
        }
        
        .search-container .hero-search {
            margin: 0;
        }
        
        /* Filter and Sort Controls */
        .all-books-section > .filter-control,
        .all-books-section > .sort-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin: 25px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            max-width: 500px;
        }
        
        .filter-control label,
        .sort-control label {
            font-weight: bold;
            color: #555;
            font-size: 15px;
        }
        
        .filter-control select,
        .sort-control select {
            padding: 12px 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: border-color 0.3s, box-shadow 0.3s;
            background: white;
            min-width: 200px;
        }
        
        .filter-control select:focus,
        .sort-control select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* Books Grid - FIXED TO 3 COLUMNS */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 50px;
            margin-bottom: 50px;
        }
        
        .book-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .book-card .book-image {
            height: 350px;
            position: relative;
        }
        
        .availability {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .availability.available {
            background: #27ae60;
            color: white;
        }
        
        .availability.unavailable {
            background: #e74c3c;
            color: white;
        }
        
        .book-details {
            padding: 20px;
        }
        
        .book-title {
            font-size: 18px;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        .book-author {
            color: #666;
            font-size: 14px;
            margin-bottom: 12px;
        }
        
        .book-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            font-size: 13px;
        }
        
        .borrowed {
            color: #3498db;
        }
        
        .book-genre {
            color: #667eea;
            font-size: 13px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .book-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .btn {
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-secondary {
            background: #27ae60;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #229954;
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
        
        .no-books {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            font-size: 18px;
            color: #666;
            background: white;
            border-radius: 12px;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 30px 0;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .btn-pagination {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .btn-pagination:hover {
            background: #5568d3;
        }
        
        .btn-pagination.disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .page-info {
            text-align: center;
            font-size: 16px;
            color: #555;
        }
        
        .total-books {
            color: #999;
            font-size: 14px;
            display: block;
            margin-top: 5px;
        }
        
        /* Responsive - tablet (2 columns) */
        @media (max-width: 1024px) {
            .featured-books {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .books-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Responsive - mobile (1 column) */
        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 32px;
            }
            
            .hero-content p {
                font-size: 16px;
            }
            
            .section-title {
                font-size: 28px;
            }
            
            .hero-search {
                flex-direction: column;
            }
            
            .filter-control,
            .sort-control {
                flex-direction: column;
                align-items: stretch !important;
            }
            
            .filter-control select,
            .sort-control select {
                width: 100%;
            }
            
            .pagination {
                flex-direction: column;
            }
            
            .featured-books {
                grid-template-columns: 1fr;
            }
            
            .books-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
        
        h2 {
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-content">
                <?php if (!$is_logged_in): ?>
                <div class="login-prompt">
                    <p><a href="authentication/login.php">Login</a> or <a href="authentication/signup.php">Sign up</a> to borrow books</p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="featured-section" id="featured">
            <h2 class="section-title"> Featured Books</h2>
            <div class="container">
                <div class="featured-books">
                    <?php foreach ($featured_books as $book): ?>
                    <div class="featured-book-card">
                        <div class="book-image">
                            <img src="<?php echo bookCoverSrc($book); ?>" 
                                 alt="<?php echo htmlspecialchars($book['title']); ?>" loading="lazy">
                            <div class="book-overlay">
                                <a href="user/book-details.php?id=<?php echo $book['id']; ?>" class="btn-view">View Details</a>
                            </div>
                        </div>
                        <div class="book-info">
                            <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p class="author">by <?php echo htmlspecialchars($book['author']); ?></p>
                            <div class="book-meta">
                                <span class="rating">⭐ <?php echo number_format($book['rating'], 1); ?></span>
                                <span class="genre"><?php echo htmlspecialchars($book['genre']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="all-books-section" id="books">
            <h2 class="section-title"> Browse Our Collection</h2>
            
            <!-- Search Bar -->
            <div class="search-container">
                <div class="hero-search">
                    <input type="text" id="search-input" placeholder="Search for books by title..." autocomplete="off">
                    <button onclick="searchBooks()">Search</button>
                </div>
            </div>
            
            <?php if ($is_admin): ?>
            <div class="admin-controls">
                <a href="admin/create.php" class="btn-admin"> Add New Book</a>
                <a href="admin/upload_cover.php" class="btn-admin"> Manage Covers</a>
                <a href="admin/dashboard.php" class="btn-admin"> Admin Dashboard</a>
            </div>
            <?php endif; ?>

            <div class="filter-control">
                <label for="genre-filter">Filter by Genre:</label>
                <select id="genre-filter" onchange="applyFilters()">
                    <option value="">All Genres</option>
                    <?php foreach ($genres as $g): ?>
                    <option value="<?php echo htmlspecialchars($g); ?>" 
                            <?php echo ($genre === $g) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($g); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sort-control">
                <label for="sort-by">Sort by:</label>
                <select id="sort-by" onchange="applyFilters()">
                    <option value="title_asc" <?php echo ($sort === 'title_asc') ? 'selected' : ''; ?>>A-Z</option>
                    <option value="title_desc" <?php echo ($sort === 'title_desc') ? 'selected' : ''; ?>>Z-A</option>
                    <option value="rating" <?php echo ($sort === 'rating') ? 'selected' : ''; ?>>Most Rated</option>
                    <option value="borrowed" <?php echo ($sort === 'borrowed') ? 'selected' : ''; ?>>Most Borrowed</option>
                </select>
            </div>
            
            <div class="container">
                <div class="books-grid">
                    <?php if (empty($books)): ?>
                        <p class="no-books">No books found matching your criteria.</p>
                    <?php else: ?>
                        <?php foreach ($books as $book): ?>
                        <div class="book-card">
                            <div class="book-image">
                                <img src="<?php echo bookCoverSrc($book); ?>" 
                                     alt="<?php echo htmlspecialchars($book['title']); ?>" loading="lazy">
                                <div class="availability <?php echo ($book['available_copies'] > 0) ? 'available' : 'unavailable'; ?>">
                                    <?php echo ($book['available_copies'] > 0) ? 'Available' : 'Unavailable'; ?>
                                </div>
                            </div>
                            <div class="book-details">
                                <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="book-author"><?php echo htmlspecialchars($book['author']); ?></p>
                                <div class="book-stats">
                                    <span class="rating">⭐ <?php echo number_format($book['rating'], 1); ?></span>
                                    <span class="borrowed"> <?php echo $book['borrow_count']; ?> borrows</span>
                                </div>
                                <p class="book-genre"><?php echo htmlspecialchars($book['genre']); ?></p>

                                <div class="book-actions">
                                    <a href="user/book-details.php?id=<?php echo $book['id']; ?>" class="btn btn-primary">View Details</a>
                                    <?php if ($is_user && $book['available_copies'] > 0): ?>
                                        <a href="user/borrow.php?id=<?php echo $book['id']; ?>" class="btn btn-secondary">Borrow</a>
                                    <?php elseif (!$is_logged_in && $book['available_copies'] > 0): ?>
                                        <a href="authentication/login.php?redirect=borrow.php?id=<?php echo $book['id']; ?>" class="btn btn-secondary">Login to Borrow</a>
                                    <?php endif; ?>
                                    <?php if ($is_admin): ?>
                                        <a href="admin/edit.php?id=<?php echo $book['id']; ?>" class="btn btn-edit">✏️ Edit</a>
                                        <a href="admin/delete.php?id=<?php echo $book['id']; ?>" class="btn btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this book?')">🗑️ Delete</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&genre=<?php echo urlencode($genre); ?>&sort=<?php echo $sort; ?>" 
                       class="btn-pagination btn-prev">← Previous</a>
                    <?php else: ?>
                    <span class="btn-pagination btn-prev disabled">← Previous</span>
                    <?php endif; ?>

                    <div class="page-info">
                        <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                        <span class="total-books">(<?php echo $total_books; ?> books)</span>
                    </div>

                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&genre=<?php echo urlencode($genre); ?>&sort=<?php echo $sort; ?>" 
                       class="btn-pagination btn-next">Next →</a>
                    <?php else: ?>
                    <span class="btn-pagination btn-next disabled">Next →</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        function applyFilters() {
            const genre = document.getElementById('genre-filter').value;
            const sort = document.getElementById('sort-by').value;
            
            let url = 'index.php?';
            if (genre) url += 'genre=' + encodeURIComponent(genre) + '&';
            url += 'sort=' + sort;
            
            window.location.href = url;
        }
        
        // Client-side search functionality
        const searchInput = document.getElementById('search-input');
        const bookCards = document.querySelectorAll('.book-card');
        
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            
            bookCards.forEach(card => {
                const title = card.querySelector('.book-title').textContent.toLowerCase();
                
                if (query === '' || title.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Check if any books are visible
            const visibleBooks = Array.from(bookCards).filter(card => card.style.display !== 'none');
            const booksGrid = document.querySelector('.books-grid');
            const noResultsMsg = document.querySelector('.search-no-results');
            
            if (visibleBooks.length === 0 && query !== '') {
                if (!noResultsMsg) {
                    const msg = document.createElement('p');
                    msg.className = 'search-no-results no-books';
                    msg.textContent = 'No books found matching "' + query + '"';
                    booksGrid.appendChild(msg);
                }
            } else {
                if (noResultsMsg) {
                    noResultsMsg.remove();
                }
            }
        });
        
        function searchBooks() {
            // Trigger the input event to filter
            searchInput.dispatchEvent(new Event('input'));
        }
        
        // Allow Enter key to search
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchBooks();
            }
        });
    </script>
</body>
</html>