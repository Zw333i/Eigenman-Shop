<?php
// merchant/store.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$merchantId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$merchant = null;
$products = [];
$topRatedProducts = [];
$isFollowing = false;
$errorMsg = '';
$successMsg = '';
$reviewCounts = [];
$avgRatings = [];

if (isset($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $errorMsg = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if ($merchantId <= 0) {
    $_SESSION['error_message'] = 'Invalid merchant ID';
    header('Location: ../index.php');
    exit;
}

// erchant details
$stmt = $conn->prepare("SELECT m.*, u.userId, u.profilePicture, u.followers, u.following 
                        FROM Merchant m
                        JOIN User u ON m.userId = u.userId 
                        WHERE m.merchantId = ?");
$stmt->bind_param("i", $merchantId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'Merchant not found';
    header('Location: ../index.php');
    exit;
}

$merchant = $result->fetch_assoc();
$stmt->close();

$isFollowing = false;
if (isset($_SESSION['userId'])) {
    $userId = $_SESSION['userId'];
    $merchantUserId = $merchant['userId'];
    
    $stmt = $conn->prepare("SELECT * FROM UserFollows WHERE userId = ? AND merchantUserId = ?");
    $stmt->bind_param("ii", $userId, $merchantUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $isFollowing = ($result->num_rows > 0);
    $stmt->close();
}

// handle follow/unfollow action
if (isset($_POST['follow_action']) && isset($_SESSION['userId'])) {
    $userId = $_SESSION['userId'];
    $merchantUserId = $merchant['userId'];
    
    if ($_POST['follow_action'] === 'follow') {
        $checkStmt = $conn->prepare("SELECT * FROM UserFollows WHERE userId = ? AND merchantUserId = ?");
        $checkStmt->bind_param("ii", $userId, $merchantUserId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();
        
        if ($checkResult->num_rows == 0) {
            $followStmt = $conn->prepare("INSERT INTO UserFollows (userId, merchantUserId) VALUES (?, ?)");
            $followStmt->bind_param("ii", $userId, $merchantUserId);
            $followStmt->execute();
            $followStmt->close();
            
            $conn->query("UPDATE User SET following = following + 1 WHERE userId = $userId");
            
            $conn->query("UPDATE User SET followers = followers + 1 WHERE userId = $merchantUserId");
            
            $message = $_SESSION['username'] . " started following your store!";
            $stmt2 = $conn->prepare("INSERT INTO Notifications (receiverId, senderId, message, timestamp) VALUES (?, ?, ?, NOW())");
            $stmt2->bind_param("iis", $merchantUserId, $userId, $message);
            $stmt2->execute();
            $stmt2->close();
            
            $isFollowing = true;
            $successMsg = "You are now following this merchant";
            
            $merchant['followers'] += 1;
        }
    } elseif ($_POST['follow_action'] === 'unfollow') {
        // remove follow record
        $unfollowStmt = $conn->prepare("DELETE FROM UserFollows WHERE userId = ? AND merchantUserId = ?");
        $unfollowStmt->bind_param("ii", $userId, $merchantUserId);
        $unfollowStmt->execute();
        
        if ($unfollowStmt->affected_rows > 0) {
            $conn->query("UPDATE User SET following = following - 1 WHERE userId = $userId");
            
            $conn->query("UPDATE User SET followers = followers - 1 WHERE userId = $merchantUserId");
            
            $merchant['followers'] -= 1;
        }
        
        $unfollowStmt->close();
        $isFollowing = false;
        $successMsg = "You have unfollowed this merchant";
    }
}

$stmt = $conn->prepare("SELECT * FROM Item WHERE merchantId = ? ORDER BY itemName");
$stmt->bind_param("i", $merchantId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

$stmt = $conn->prepare("SELECT i.*, AVG(o.rating) as avg_rating, COUNT(o.rating) as review_count 
                        FROM Item i 
                        LEFT JOIN Orders o ON i.itemId = o.itemId 
                        WHERE i.merchantId = ? AND o.rating IS NOT NULL 
                        GROUP BY i.itemId 
                        ORDER BY avg_rating DESC 
                        LIMIT 4");
$stmt->bind_param("i", $merchantId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $topRatedProducts[] = $row;
}
$stmt->close();

foreach ($products as $product) {
    $itemId = $product['itemId'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as review_count FROM Orders WHERE itemId = ? AND rating IS NOT NULL");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $reviewCounts[$itemId] = $row['review_count'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating FROM Orders WHERE itemId = ? AND rating IS NOT NULL");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $avgRatings[$itemId] = $row['avg_rating'] ?? 0;
    $stmt->close();
}

if (isset($_SESSION['userId'])) {
    $userId = $_SESSION['userId'];
    $viewType = 'store_view';
    $stmt = $conn->prepare("INSERT INTO Search (userId, merchantId, keyword, searchType) VALUES (?, ?, ?, ?)");
    $emptyKeyword = '';
    $stmt->bind_param("iiss", $userId, $merchantId, $emptyKeyword, $viewType);
    $stmt->execute();
    $stmt->close();
}

include_once '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">

<div class="container my-5 store-page-container">
    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
            <?php echo $errorMsg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
            <?php echo $successMsg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <nav aria-label="breadcrumb" class="animate__animated animate__fadeIn animate__faster">
        <ol class="breadcrumb bg-transparent p-0">
            <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="../product/index.php" class="text-decoration-none">Products</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($merchant['storeName']); ?></li>
        </ol>
    </nav>

    <div class="store-profile-container animate__animated animate__fadeIn">
        <div class="card store-profile-card">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <div class="store-profile-img-container">
                            <?php if (!empty($merchant['profilePicture']) && file_exists("../" . $merchant['profilePicture'])): ?>
                                <img src="../<?php echo htmlspecialchars($merchant['profilePicture']); ?>" class="store-profile-img" alt="<?php echo htmlspecialchars($merchant['storeName']); ?>">
                            <?php else: ?>
                                <img src="../assets/images/profile-placeholder.jpg" class="store-profile-img" alt="Store Profile">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <h1 class="store-name mb-2"><?php echo htmlspecialchars($merchant['storeName']); ?></h1>
                        <div class="store-stats d-flex flex-wrap gap-3 my-3">
                            <div class="store-stat-item">
                                <i class="bi bi-box-seam"></i>
                                <span><?php echo count($products); ?> Products</span>
                            </div>
                            <div class="store-stat-item">
                                <i class="bi bi-people"></i>
                                <span><?php echo $merchant['followers']; ?> Followers</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 text-md-end mt-3 mt-md-0">
                        <?php if (isset($_SESSION['userId']) && $_SESSION['role'] === 'customer'): ?>
                            <div class="d-flex flex-column gap-2">
                                <?php if ($isFollowing): ?>
                                    <form method="POST">
                                        <input type="hidden" name="follow_action" value="unfollow">
                                        <button type="submit" class="btn btn-outline-secondary follow-btn w-100">
                                            <i class="bi bi-person-check"></i> Following
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="follow_action" value="follow">
                                        <button type="submit" class="btn btn-primary follow-btn w-100">
                                            <i class="bi bi-person-plus"></i> Follow
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="../chats/index.php?recipient=<?php echo $merchant['userId']; ?>" class="btn btn-outline-info chat-btn">
                                    <i class="bi bi-chat-dots"></i> Message Seller
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($topRatedProducts)): ?>
    <div class="top-rated-products-section mt-4 animate__animated animate__fadeIn animate__delay-1s">
        <div class="section-header">
            <h2><i class="bi bi-star-fill me-2"></i>Top Rated Products</h2>
        </div>
        <div class="row g-4 mt-2">
            <?php foreach ($topRatedProducts as $product): ?>
                <div class="col-sm-6 col-md-3 mb-4">
                    <div class="card product-card h-100">
                        <a href="../product/details.php?id=<?php echo $product['itemId']; ?>" class="text-decoration-none">
                            <div class="product-img-container">
                                <?php if (!empty($product['picture']) && file_exists("../" . $product['picture'])): ?>
                                    <img src="../<?php echo htmlspecialchars($product['picture']); ?>" class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['itemName']); ?>">
                                <?php else: ?>
                                    <img src="../assets/images/product-placeholder.jpg" class="card-img-top product-img" alt="Product Image">
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title product-title"><?php echo htmlspecialchars($product['itemName']); ?></h5>
                                <p class="card-text product-price">₱<?php echo number_format($product['itemPrice'], 2); ?></p>
                                <div class="product-rating">
                                    <?php 
                                    $rating = round($product['avg_rating']);
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '<i class="bi bi-star-fill text-warning"></i>';
                                        } else {
                                            echo '<i class="bi bi-star text-warning"></i>';
                                        }
                                    }
                                    ?>
                                    <span class="ms-1">(<?php echo $product['review_count']; ?>)</span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- All Products Section -->
    <div class="all-products-section mt-4 animate__animated animate__fadeIn animate__delay-2s">
        <div class="section-header">
            <h2><i class="bi bi-grid me-2"></i>All Products</h2>
        </div>
        
        <!-- Filter Options -->
        <div class="filter-options mb-4">
            <div class="row">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchInput" placeholder="Search products...">
                        <button class="btn btn-outline-secondary" type="button" id="searchButton">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="sortSelect">
                        <option value="name_asc">Name (A-Z)</option>
                        <option value="name_desc">Name (Z-A)</option>
                        <option value="price_asc">Price (Low to High)</option>
                        <option value="price_desc">Price (High to Low)</option>
                        <option value="rating_desc">Rating (High to Low)</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Products Grid -->
        <div class="row g-4" id="productsGrid">
            <?php if (empty($products)): ?>
                <div class="col-12 text-center py-5">
                    <div class="empty-state">
                        <i class="bi bi-box-seam fs-1"></i>
                        <h4 class="mt-3">No Products Yet</h4>
                        <p class="text-muted">This merchant hasn't listed any products yet.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-6 col-md-4 col-lg-3 mb-4 product-item" 
                         data-name="<?php echo strtolower(htmlspecialchars($product['itemName'])); ?>"
                         data-price="<?php echo $product['itemPrice']; ?>"
                         data-rating="<?php echo $avgRatings[$product['itemId']] ?? 0; ?>">
                        <div class="card product-card h-100">
                            <a href="../product/details.php?id=<?php echo $product['itemId']; ?>" class="text-decoration-none">
                                <div class="product-img-container">
                                    <?php if (!empty($product['picture']) && file_exists("../" . $product['picture'])): ?>
                                        <img src="../<?php echo htmlspecialchars($product['picture']); ?>" class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['itemName']); ?>">
                                    <?php else: ?>
                                        <img src="../assets/images/product-placeholder.jpg" class="card-img-top product-img" alt="Product Image">
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title product-title"><?php echo htmlspecialchars($product['itemName']); ?></h5>
                                    <p class="card-text product-price">₱<?php echo number_format($product['itemPrice'], 2); ?></p>
                                    <div class="product-rating">
                                        <?php 
                                        $rating = round($avgRatings[$product['itemId']] ?? 0);
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $rating) {
                                                echo '<i class="bi bi-star-fill text-warning"></i>';
                                            } else {
                                                echo '<i class="bi bi-star text-warning"></i>';
                                            }
                                        }
                                        ?>
                                        <span class="ms-1">(<?php echo $reviewCounts[$product['itemId']] ?? 0; ?>)</span>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent border-top-0">
                                    <div class="stock-info">
                                        <small class="<?php echo $product['quantity'] > 10 ? 'text-success' : ($product['quantity'] > 0 ? 'text-warning' : 'text-danger'); ?>">
                                            <?php 
                                            if ($product['quantity'] > 10) {
                                                echo '<i class="bi bi-check-circle-fill"></i> In Stock';
                                            } elseif ($product['quantity'] > 0) {
                                                echo '<i class="bi bi-exclamation-circle-fill"></i> Low Stock: ' . $product['quantity'] . ' left';
                                            } else {
                                                echo '<i class="bi bi-x-circle-fill"></i> Out of Stock';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Empty State for Search Results -->
        <div id="noProductsFound" class="text-center py-5" style="display: none;">
            <div class="empty-state">
                <i class="bi bi-search fs-1"></i>
                <h4 class="mt-3">No Products Found</h4>
                <p class="text-muted">Try a different search term or browse all products.</p>
                <button class="btn btn-outline-primary mt-2" id="resetSearchBtn">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset Search
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --primary-color: #4361ee;
        --accent-color: #3a0ca3;
        --light-bg: #f8f9fb;
        --border-radius: 12px;
        --box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        --hover-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        --transition-speed: 0.3s;
    }
    
    .store-page-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .store-profile-card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        overflow: hidden;
        background-color: white;
    }
    
    .store-profile-img-container {
        width: 120px;
        height: 120px;
        margin: 0 auto;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid var(--primary-color);
        box-shadow: var(--box-shadow);
    }
    
    .store-profile-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .store-name {
        font-size: 1.8rem;
        font-weight: 700;
        color: #333;
    }
    
    .store-stats {
        display: flex;
        align-items: center;
    }
    
    .store-stat-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #666;
        font-size: 1rem;
    }
    
    .store-stat-item i {
        color: var(--primary-color);
    }
    
    .follow-btn, .chat-btn {
        border-radius: 50px;
        padding: 8px 16px;
        font-weight: 600;
        transition: all var(--transition-speed);
    }
    
    .follow-btn:hover, .chat-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    /* Section Headers */
    .section-header {
        margin-bottom: 1.5rem;
    }
    
    .section-header h2 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #333;
        margin: 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #eee;
    }
    
    .product-card {
        border: none;
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--box-shadow);
        transition: all var(--transition-speed);
    }
    
    .product-card:hover {
        transform: translateY(-10px);
        box-shadow: var(--hover-shadow);
    }
    
    .product-img-container {
        height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--light-bg);
        overflow: hidden;
    }
    
    .product-img {
        height: 160px;
        width: 100%;
        object-fit: contain;
        transition: transform var(--transition-speed);
    }
    
    .product-card:hover .product-img {
        transform: scale(1.05);
    }
    
    .product-title {
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        height: 48px;
    }
    
    .product-price {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 8px;
    }
    
    .product-rating {
        display: flex;
        align-items: center;
    }
    
    .product-rating i {
        color: #ffc107;
    }
    
    .product-rating span {
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    /* Filter Options */
    .filter-options {
        background-color: white;
        border-radius: var(--border-radius);
        padding: 15px;
        box-shadow: var(--box-shadow);
        margin-bottom: 1.5rem;
    }
    
    /* Empty State */
    .empty-state {
        padding: 40px;
        text-align: center;
        color: #6c757d;
    }
    
    .empty-state i {
        font-size: 3rem;
        color: #adb5bd;
        margin-bottom: 1rem;
    }
    
    .empty-state h4 {
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    
    /* Responsive Styles */
    @media (max-width: 768px) {
        .store-profile-img-container {
            width: 90px;
            height: 90px;
        }
        
        .store-name {
            font-size: 1.5rem;
            margin-top: 1rem;
            text-align: center;
        }
        
        .store-stats {
            justify-content: center;
            margin-top: 0.5rem;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Follow button hover effect for better UX
        const followBtn = document.querySelector('.follow-btn');
        if (followBtn && followBtn.classList.contains('btn-outline-secondary')) {
            followBtn.addEventListener('mouseover', function() {
                this.innerHTML = '<i class="bi bi-person-dash"></i> Unfollow';
                this.classList.add('btn-outline-danger');
                this.classList.remove('btn-outline-secondary');
            });
            
            followBtn.addEventListener('mouseout', function() {
                this.innerHTML = '<i class="bi bi-person-check"></i> Following';
                this.classList.add('btn-outline-secondary');
                this.classList.remove('btn-outline-danger');
            });
        }
        
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const searchButton = document.getElementById('searchButton');
        const resetSearchBtn = document.getElementById('resetSearchBtn');
        const productsGrid = document.getElementById('productsGrid');
        const noProductsFound = document.getElementById('noProductsFound');
        const productItems = document.querySelectorAll('.product-item');
        
        function performSearch() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            let foundProducts = false;
            
            productItems.forEach(item => {
                const productName = item.getAttribute('data-name');
                if (productName.includes(searchTerm)) {
                    item.style.display = 'block';
                    foundProducts = true;
                } else {
                    item.style.display = 'none';
                }
            });
            
            if (foundProducts) {
                productsGrid.style.display = 'flex';
                noProductsFound.style.display = 'none';
            } else {
                productsGrid.style.display = 'none';
                noProductsFound.style.display = 'block';
            }
        }
        
        if (searchButton) {
            searchButton.addEventListener('click', performSearch);
        }
        
        if (searchInput) {
            searchInput.addEventListener('keyup', function(event) {
                if (event.key === 'Enter') {
                    performSearch();
                }
            });
        }
        
        if (resetSearchBtn) {
            resetSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                productItems.forEach(item => {
                    item.style.display = 'block';
                });
                productsGrid.style.display = 'flex';
                noProductsFound.style.display = 'none';
            });
        }
        
        // Sorting functionality
        const sortSelect = document.getElementById('sortSelect');
        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                const sortValue = this.value;
                const itemsArray = Array.from(productItems);
                
                itemsArray.sort((a, b) => {
                    if (sortValue === 'name_asc') {
                        return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
                    } else if (sortValue === 'name_desc') {
                        return b.getAttribute('data-name').localeCompare(a.getAttribute('data-name'));
                    } else if (sortValue === 'price_asc') {
                        return parseFloat(a.getAttribute('data-price')) - parseFloat(b.getAttribute('data-price'));
                    } else if (sortValue === 'price_desc') {
                        return parseFloat(b.getAttribute('data-price')) - parseFloat(a.getAttribute('data-price'));
                    } else if (sortValue === 'rating_desc') {
                        return parseFloat(b.getAttribute('data-rating')) - parseFloat(a.getAttribute('data-rating'));
                    }
                });
                
                // Clear the grid and re-append sorted items
                const parent = productsGrid;
                parent.innerHTML = '';
                
                // Check if we have products to display
                if (itemsArray.length > 0) {
                    itemsArray.forEach(item => {
                        parent.appendChild(item);
                    });
                    productsGrid.style.display = 'flex';
                    noProductsFound.style.display = 'none';
                } else {
                    productsGrid.style.display = 'none';
                    noProductsFound.style.display = 'block';
                }
            });
        }
        
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
        
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.classList.add('animate__animated', 'animate__pulse');
            });
            
            card.addEventListener('mouseleave', function() {
                this.classList.remove('animate__animated', 'animate__pulse');
            });
        });
    });
</script>

<?php
if (isset($_SESSION['userId'])) {
    $analyticsStmt = $conn->prepare("INSERT INTO Analytics (userId, merchantId, actionType, timestamp) 
                                     VALUES (?, ?, 'store_view', NOW())");
    $analyticsStmt->bind_param("ii", $_SESSION['userId'], $merchantId);
    $analyticsStmt->execute();
    $analyticsStmt->close();
}

include_once '../includes/footer.php';
?>