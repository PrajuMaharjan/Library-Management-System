<?php
session_start();
require_once 'config/db.php';

function bookCoverSrc($book) {
    if (!empty($book['cover_image'])) {
        return 'data:image/jpeg;base64,' . base64_encode($book['cover_image']);
    }
    return 'https://via.placeholder.com/300x450/667eea/ffffff?text=No+Cover';
}

$is_logged_in=isset($_SESSION['user_id']);
$user_type=$is_logged_in ? $_SESSION['role'] : null;
$is_admin=($user_type==='admin');
$is_user=($user_type==='user');

$genre=isset($_GET['genre']) ? $_GET['genre'] : '';
$sort=isset($_GET['sort']) ? $_GET['sort'] : 'title_asc';
$page=isset($_GET['page']) ? (int)$_GET['page'] : 1;
$books_per_page=9;
$offset=($page-1)*$books_per_page;

$sql = "SELECT b.*,bc.image AS cover_image FROM books b LEFT JOIN book_covers bc ON bc.book_name=b.title WHERE 1=1";
$params=[];

if (!empty($genre)) {
    $sql .= " AND b.genre = :genre";
    $params[':genre'] = $genre;
}

switch($sort){
    case 'title_asc':
        $sql .=" ORDER BY b.title ASC";
        break;
    case 'title_desc':
        $sql .=" ORDER BY b.title DESC";
        break;
    case 'rating':
        $sql .=" ORDER BY b.rating DESC";
        break;
    case 'borrowed':
        $sql .=" ORDER BY b.borrow_count DESC";
        break;
    default:
        $sql .=" ORDER BY b.title ASC";
}

$count_sql="SELECT COUNT(*) FROM books b WHERE 1=1";
if (!empty($genre)) {
    $count_sql.=" AND b.genre = :genre";
}
$count_stmt=$pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_books=$count_stmt->fetchColumn();
$total_pages=ceil($total_books/$books_per_page);

$sql.=" LIMIT :limit OFFSET :offset";
$stmt=$pdo->prepare($sql);
foreach($params as $key => $value) {
    $stmt->bindValue($key,$value);
}
$stmt->bindValue(':limit',$books_per_page,PDO::PARAM_INT);
$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
$stmt->execute();
$books=$stmt->fetchAll(PDO::FETCH_ASSOC);

$featured_stmt= $pdo->query("
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

$genres_stmt = $pdo->query("SELECT DISTINCT genre FROM books ORDER BY genre");
$genres = $genres_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library ManageMent System - Home</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main>
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