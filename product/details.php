<?php
// product/details.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$related_products = [];
$isFollowing = false;
$errorMsg = '';
$successMsg = '';
$ratings = [];
$avgRating = 0;
$totalRatings = 0;

if (isset($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $errorMsg = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if ($itemId <= 0) {
    $_SESSION['error_message'] = 'Invalid product ID';
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare("SELECT i.*, m.storeName, u.userId as merchantUserId, u.profilePicture as merchantProfilePic 
                        FROM Item i 
                        JOIN Merchant m ON i.merchantId = m.merchantId 
                        JOIN User u ON m.userId = u.userId
                        WHERE i.itemId = ?");
$stmt->bind_param("i", $itemId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'Product not found';
    header('Location: index.php');
    exit;
}

$product = $result->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT o.rating, o.review, o.ratingDate, 
                        u.firstName, u.lastName, u.profilePicture 
                        FROM Orders o 
                        JOIN User u ON o.userId = u.userId
                        WHERE o.itemId = ? AND o.rating IS NOT NULL
                        ORDER BY o.ratingDate DESC");
$stmt->bind_param("i", $itemId);
$stmt->execute();
$ratingResult = $stmt->get_result();

while ($row = $ratingResult->fetch_assoc()) {
    $ratings[] = $row;
    $avgRating += $row['rating'];
}
$stmt->close();

$totalRatings = count($ratings);
if ($totalRatings > 0) {
    $avgRating = $avgRating / $totalRatings;
}

$isFollowing = false;
if (isset($_SESSION['userId'])) {
    $userId = $_SESSION['userId'];
    $merchantUserId = $product['merchantUserId'];
    
    $conn->query("CREATE TABLE IF NOT EXISTS UserFollows (
        userId INT NOT NULL,
        merchantUserId INT NOT NULL,
        PRIMARY KEY (userId, merchantUserId)
    )");
    
    $stmt = $conn->prepare("SELECT * FROM UserFollows WHERE userId = ? AND merchantUserId = ?");
    $stmt->bind_param("ii", $userId, $merchantUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $isFollowing = ($result->num_rows > 0);
    $stmt->close();
}

// follow unfollow
if (isset($_POST['follow_action']) && isset($_SESSION['userId'])) {
    $userId = $_SESSION['userId'];
    $merchantUserId = $product['merchantUserId'];
    
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
        }
    } elseif ($_POST['follow_action'] === 'unfollow') {
        $unfollowStmt = $conn->prepare("DELETE FROM UserFollows WHERE userId = ? AND merchantUserId = ?");
        $unfollowStmt->bind_param("ii", $userId, $merchantUserId);
        $unfollowStmt->execute();
        
        if ($unfollowStmt->affected_rows > 0) {
            $conn->query("UPDATE User SET following = following - 1 WHERE userId = $userId");
            
            $conn->query("UPDATE User SET followers = followers - 1 WHERE userId = $merchantUserId");
        }
        
        $unfollowStmt->close();
        $isFollowing = false;
        $successMsg = "You have unfollowed this merchant";
    }
}

$merchantId = $product['merchantId'];
$stmt = $conn->prepare("SELECT * FROM Item WHERE merchantId = ? AND itemId != ? LIMIT 4");
$stmt->bind_param("ii", $merchantId, $itemId);
$stmt->execute();
$relatedResult = $stmt->get_result();

while ($row = $relatedResult->fetch_assoc()) {
    $related_products[] = $row;
}
$stmt->close();

if (isset($_SESSION['userId'])) {
    $userId = $_SESSION['userId'];
    $viewType = 'item_view';
    $stmt = $conn->prepare("INSERT INTO Search (userId, itemId, keyword, searchType) VALUES (?, ?, ?, ?)");
    $emptyKeyword = '';
    $stmt->bind_param("iiss", $userId, $itemId, $emptyKeyword, $viewType);
    $stmt->execute();
    $stmt->close();
}

include_once '../includes/header.php';
?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">

<div class="container my-5 product-details-container">
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
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Products</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['itemName']); ?></li>
        </ol>
    </nav>

    <div class="product-main-content animate__animated animate__fadeIn animate__slow">
        <div class="row g-4">
            <!-- Product Image -->
            <div class="col-lg-6 mb-4">
                <div class="product-image-card" id="productImageCard">
                    <?php if (!empty($product['picture']) && file_exists("../" . $product['picture'])): ?>
                        <img src="../<?php echo htmlspecialchars($product['picture']); ?>" class="img-fluid product-detail-img" alt="<?php echo htmlspecialchars($product['itemName']); ?>">
                    <?php else: ?>
                        <img src="../assets/images/product-placeholder.jpg" class="img-fluid product-detail-img" alt="Product Image">
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Details -->
            <div class="col-lg-6">
                <div class="product-info-card p-4">
                    <span class="badge bg-accent mb-2 fade-in-element"><?php echo htmlspecialchars($product['brand']); ?></span>
                    <h1 class="product-title mb-2 fade-in-element"><?php echo htmlspecialchars($product['itemName']); ?></h1>
                    <div class="product-price mb-3 fade-in-element">₱<?php echo number_format($product['itemPrice'], 2); ?></div>
                    
                    <!-- Product Rating Display -->
                    <?php if ($totalRatings > 0): ?>
                    <div class="product-rating mb-3 fade-in-element">
                        <div class="d-flex align-items-center">
                            <div class="star-rating me-2">
                                <?php
                                // Display star rating
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= round($avgRating)) {
                                        echo '<i class="bi bi-star-fill text-warning"></i>';
                                    } elseif ($i - 0.5 <= $avgRating) {
                                        echo '<i class="bi bi-star-half text-warning"></i>';
                                    } else {
                                        echo '<i class="bi bi-star text-warning"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <span class="rating-value"><?php echo number_format($avgRating, 1); ?></span>
                            <span class="text-muted ms-2">(<?php echo $totalRatings; ?> <?php echo $totalRatings == 1 ? 'review' : 'reviews'; ?>)</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="stock-info mb-4 fade-in-element">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-box me-2"></i>
                            <span class="<?php echo $product['quantity'] > 10 ? 'text-success' : ($product['quantity'] > 0 ? 'text-warning' : 'text-danger'); ?>">
                                <?php 
                                if ($product['quantity'] > 10) {
                                    echo 'In Stock';
                                } elseif ($product['quantity'] > 0) {
                                    echo 'Low Stock: ' . $product['quantity'] . ' remaining';
                                } else {
                                    echo 'Out of Stock';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="store-info-card mb-4 fade-in-element">
                        <div class="d-flex align-items-center">
                            <div class="store-image me-3">
                                <?php if (!empty($product['merchantProfilePic']) && file_exists("../" . $product['merchantProfilePic'])): ?>
                                    <img src="../<?php echo htmlspecialchars($product['merchantProfilePic']); ?>" class="rounded-circle" width="50" height="50" alt="Merchant Profile">
                                <?php else: ?>
                                    <img src="../assets/images/profile-placeholder.jpg" class="rounded-circle" width="50" height="50" alt="Merchant Profile">
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="mb-1 fw-bold store-name"><?php echo htmlspecialchars($product['storeName']); ?></p>
                                <div class="store-actions d-flex flex-wrap">
                                    <a href="../merchant/store.php?id=<?php echo $product['merchantId']; ?>" class="btn btn-sm btn-outline-secondary me-2 mb-2 store-btn">
                                        <i class="bi bi-shop"></i> Visit Store
                                    </a>
                                    <?php if (isset($_SESSION['userId']) && $_SESSION['role'] === 'customer'): ?>
                                        <?php if ($isFollowing): ?>
                                            <form method="POST" class="me-2 mb-2">
                                                <input type="hidden" name="follow_action" value="unfollow">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary follow-btn">
                                                    <i class="bi bi-person-check"></i> Following
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="me-2 mb-2">
                                                <input type="hidden" name="follow_action" value="follow">
                                                <button type="submit" class="btn btn-sm btn-primary follow-btn">
                                                    <i class="bi bi-person-plus"></i> Follow
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="../chats/index.php?recipient=<?php echo $product['merchantUserId']; ?>" class="btn btn-sm btn-outline-info mb-2 chat-btn">
                                            <i class="bi bi-chat-dots"></i> Message Seller
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (isset($_SESSION['userId']) && $_SESSION['role'] === 'customer'): ?>
                        <div class="purchase-section mb-4 fade-in-element">
                            <div class="row align-items-center">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="quantity-selector">
                                        <button type="button" id="decreaseQuantity">
                                            <i class="bi bi-dash"></i>
                                        </button>
                                        <input type="number" id="quantity" class="form-control" value="1" min="1" max="<?php echo $product['quantity']; ?>">
                                        <button type="button" id="increaseQuantity">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="d-grid gap-2 d-md-flex">
                                        <button id="addToCartBtn" class="btn btn-outline-primary flex-grow-1 btn-action">
                                            <i class="bi bi-cart-plus"></i> Add to Cart
                                        </button>
                                        <button id="buyNowBtn" class="btn btn-primary flex-grow-1 btn-action">
                                            <i class="bi bi-bag-check"></i> Buy Now
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Description -->
    <div class="product-details-card mt-4 animate__animated animate__fadeIn animate__delay-1s">
        <div class="card-header">
            <h4><i class="bi bi-info-circle me-2"></i>Product Details</h4>
        </div>
        <div class="card-body">
            <div class="row mb-3 spec-row">
                <div class="col-md-3">
                    <strong>Brand:</strong>
                </div>
                <div class="col-md-9">
                    <?php echo htmlspecialchars($product['brand']); ?>
                </div>
            </div>
            <div class="row mb-3 spec-row">
                <div class="col-md-3">
                    <strong>Store:</strong>
                </div>
                <div class="col-md-9">
                    <?php echo htmlspecialchars($product['storeName']); ?>
                </div>
            </div>
            <div class="row mb-3 spec-row">
                <div class="col-md-3">
                    <strong>Stock:</strong>
                </div>
                <div class="col-md-9">
                    <?php echo $product['quantity']; ?> items available
                </div>
            </div>
            <?php if (!empty($product['description'])): ?>
            <div class="row mb-3 spec-row">
                <div class="col-md-3">
                    <strong>Description:</strong>
                </div>
                <div class="col-md-9">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Product Reviews Section -->
    <?php if ($totalRatings > 0): ?>
    <div class="product-reviews-card mt-4 animate__animated animate__fadeIn animate__delay-1s">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4><i class="bi bi-star me-2"></i>Customer Reviews</h4>
            <div class="review-summary">
                <span class="average-rating"><?php echo number_format($avgRating, 1); ?></span>
                <span class="text-muted">out of 5</span>
            </div>
        </div>
        <div class="card-body">
            <?php foreach ($ratings as $index => $rating): ?>
                <div class="review-item <?php echo $index > 0 ? 'border-top pt-3 mt-3' : ''; ?>">
                    <div class="d-flex mb-2">
                        <div class="reviewer-image me-3">
                            <?php if (!empty($rating['profilePicture']) && file_exists("../" . $rating['profilePicture'])): ?>
                                <img src="../<?php echo htmlspecialchars($rating['profilePicture']); ?>" class="rounded-circle" width="40" height="40" alt="Reviewer">
                            <?php else: ?>
                                <img src="../assets/images/profile-placeholder.jpg" class="rounded-circle" width="40" height="40" alt="Reviewer">
                            <?php endif; ?>
                        </div>
                        <div class="reviewer-info">
                            <h6 class="mb-0"><?php echo htmlspecialchars($rating['firstName'] . ' ' . $rating['lastName']); ?></h6>
                            <div class="d-flex align-items-center mt-1">
                                <div class="star-rating me-2">
                                    <?php
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating['rating']) {
                                            echo '<i class="bi bi-star-fill text-warning"></i>';
                                        } else {
                                            echo '<i class="bi bi-star text-warning"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($rating['ratingDate'])); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($rating['review'])): ?>
                        <div class="review-content mt-2">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($rating['review'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
        <div class="related-products-section mt-4 animate__animated animate__fadeIn animate__delay-2s">
            <div class="section-header">
                <h4><i class="bi bi-grid me-2"></i>More from this Store</h4>
            </div>
            <div class="related-products-container">
                <div class="row g-4">
                    <?php foreach ($related_products as $related): ?>
                        <div class="col-6 col-md-3 mb-3">
                            <a href="details.php?id=<?php echo $related['itemId']; ?>" class="text-decoration-none related-product-link">
                                <div class="card h-100 product-card">
                                    <div class="product-img-container">
                                        <?php if (!empty($related['picture']) && file_exists("../" . $related['picture'])): ?>
                                            <img src="../<?php echo htmlspecialchars($related['picture']); ?>" class="card-img-top related-product-img" alt="<?php echo htmlspecialchars($related['itemName']); ?>">
                                        <?php else: ?>
                                            <img src="../assets/images/product-placeholder.jpg" class="card-img-top related-product-img" alt="Product Image">
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title product-title"><?php echo htmlspecialchars($related['itemName']); ?></h6>
                                        <p class="card-text product-price">₱<?php echo number_format($related['itemPrice'], 2); ?></p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Hidden form for Buy Now functionality -->
<form id="buyNowForm" action="../order/checkout.php" method="POST" style="display: none;">
    <input type="hidden" name="direct_buy" value="1">
    <input type="hidden" name="direct_buy_item" value="<?php echo $product['itemId']; ?>">
    <input type="hidden" name="direct_buy_quantity" id="buyNowQuantity" value="1">
</form>

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

    .product-details-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    /* Product Image */
    .product-image-card {
        background-color: var(--light-bg);
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        padding: 20px;
        overflow: hidden;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform var(--transition-speed), box-shadow var(--transition-speed);
    }
    
    .product-image-card:hover {
        box-shadow: var(--hover-shadow);
        transform: translateY(-5px);
    }
    
    .product-detail-img {
        width: 100%;
        height: 400px;
        object-fit: contain;
        border-radius: 8px;
    }
    
    /* Product Info */
    .product-info-card {
        height: 100%;
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
    }
    
    .product-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: #333;
    }
    
    .product-price {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary-color);
    }
    
    /* Rating Styles */
    .star-rating {
        letter-spacing: 2px;
    }
    
    .rating-value {
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    .average-rating {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--primary-color);
    }
    
    .badge.bg-accent {
        background-color: var(--accent-color);
        font-size: 0.85rem;
        padding: 0.5em 0.8em;
    }
    
    /* Store Info */
    .store-info-card {
        background-color: var(--light-bg);
        border-radius: var(--border-radius);
        padding: 15px;
    }
    
    .store-name {
        color: #333;
        font-size: 1.1rem;
    }
    
    .store-btn, .follow-btn, .chat-btn {
        border-radius: 50px;
        transition: all var(--transition-speed);
    }
    
    .store-btn:hover, .follow-btn:hover, .chat-btn:hover {
        transform: translateY(-2px);
    }
    
    /* Quantity Selector */
    .quantity-selector {
        display: flex;
        align-items: center;
        border: 1px solid #ced4da;
        border-radius: 6px;
        width: fit-content;
        overflow: hidden;
    }
    
    .quantity-selector input {
        width: 60px;
        text-align: center;
        border: none;
        padding: 6px 0;
        margin: 0;
        border-left: 1px solid #ced4da;
        border-right: 1px solid #ced4da;
        font-weight: 600;
    }
    
    .quantity-selector input:focus {
        box-shadow: none;
        outline: none;
    }
    
    .quantity-selector button {
        background: #f8f9fa;
        border: none;
        width: 34px;
        height: 34px;
        padding: 0;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .quantity-selector button:hover {
        background-color: #e9ecef;
    }
    
    .quantity-selector button:focus {
        outline: none;
        box-shadow: none;
    }
    
    /* Action Buttons */
    .btn-action {
        border-radius: var(--border-radius);
        padding: 10px 20px;
        font-weight: 600;
        transition: all var(--transition-speed);
    }
    
    .btn-action:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    /* Product Details */
    .product-details-card, .product-reviews-card {
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--box-shadow);
        border: none;
    }
    
    .product-details-card .card-header, .product-reviews-card .card-header {
        background-color: white;
        border-bottom: 1px solid #eee;
        padding: 1.2rem 1.5rem;
    }
    
    .product-details-card .card-header h4, .product-reviews-card .card-header h4 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: #333;
    }
    
    .product-details-card .card-body, .product-reviews-card .card-body {
        padding: 1.5rem;
    }
    
    .spec-row {
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .spec-row:last-child {
        border-bottom: none;
    }
    
    /* Review Section */
    .review-item {
        position: relative;
    }
    
    .reviewer-image img {
        object-fit: cover;
    }
    
    .review-content {
        line-height: 1.5;
        color: #555;
    }
    
    /* Related Products */
    .related-products-section {
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--box-shadow);
    }
    
    .section-header {
        background-color: white;
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid #eee;
    }
    
    
    .section-header h4 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: #333;
    }
    
    .related-products-container {
        background-color: white;
        padding: 1.5rem;
    }
    
    .product-card {
        border: none;
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        transition: all var(--transition-speed);
    }
    
    .product-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--hover-shadow);
    }
    
    .product-img-container {
        height: 180px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--light-bg);
        overflow: hidden;
    }
    
    .related-product-img {
        height: 140px;
        width: 100%;
        object-fit: contain;
        transition: transform var(--transition-speed);
    }
    
    .product-card:hover .related-product-img {
        transform: scale(1.05);
    }
    
    .product-title {
        font-weight: 600;
        color: #333;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 8px;
    }
    
    .related-product-link {
        color: inherit;
    }
    
    .related-product-link:hover {
        text-decoration: none;
        color: inherit;
    }
    
    .fade-in-element {
        opacity: 0;
        animation: fadeIn 0.8s ease forwards;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .fade-in-element:nth-child(1) { animation-delay: 0.1s; }
    .fade-in-element:nth-child(2) { animation-delay: 0.2s; }
    .fade-in-element:nth-child(3) { animation-delay: 0.3s; }
    .fade-in-element:nth-child(4) { animation-delay: 0.4s; }
    .fade-in-element:nth-child(5) { animation-delay: 0.5s; }
    
    @media (max-width: 768px) {
        .product-title {
            font-size: 1.5rem;
        }
        
        .product-price {
            font-size: 1.3rem;
        }
        
        .product-detail-img {
            height: 300px;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const quantityInput = document.getElementById('quantity');
        const decreaseBtn = document.getElementById('decreaseQuantity');
        const increaseBtn = document.getElementById('increaseQuantity');
        
        if (decreaseBtn && increaseBtn && quantityInput) {
            const maxQuantity = parseInt(quantityInput.getAttribute('max'), 10);
            
            decreaseBtn.addEventListener('click', function() {
                let currentValue = parseInt(quantityInput.value, 10);
                if (currentValue > 1) {
                    quantityInput.value = currentValue - 1;
                }
            });
            
            increaseBtn.addEventListener('click', function() {
                let currentValue = parseInt(quantityInput.value, 10);
                if (currentValue < maxQuantity) {
                    quantityInput.value = currentValue + 1;
                }
            });
            
            quantityInput.addEventListener('change', function() {
                let currentValue = parseInt(quantityInput.value, 10);
                if (isNaN(currentValue) || currentValue < 1) {
                    quantityInput.value = 1;
                } else if (currentValue > maxQuantity) {
                    quantityInput.value = maxQuantity;
                }
            });
        }
        
        // add to cart dito
        const addToCartBtn = document.getElementById('addToCartBtn');
        if (addToCartBtn) {
            addToCartBtn.addEventListener('click', function() {
                const quantity = document.getElementById('quantity').value;
                const itemId = <?php echo $product['itemId']; ?>;
                
                this.classList.add('animate__animated', 'animate__pulse');
                
                setTimeout(() => {
                    window.location.href = `../cart/add.php?itemId=${itemId}&quantity=${quantity}`;
                }, 300);
            });
        }
        
        const buyNowBtn = document.getElementById('buyNowBtn');
        if (buyNowBtn) {
            buyNowBtn.addEventListener('click', function() {
                const quantity = document.getElementById('quantity').value;
                
                document.getElementById('buyNowQuantity').value = quantity;
                
                this.classList.add('animate__animated', 'animate__pulse');
                
                setTimeout(() => {
                    document.getElementById('buyNowForm').submit();
                }, 300);
            });
        }
        
        const productImageCard = document.getElementById('productImageCard');
        if (productImageCard) {
            productImageCard.addEventListener('mouseover', function() {
                this.style.transform = 'scale(1.02)';
            });
            
            productImageCard.addEventListener('mouseout', function() {
                this.style.transform = 'translateY(-5px)';
            });
        }
        
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
    });
</script>

<?php include_once '../includes/footer.php'; ?>