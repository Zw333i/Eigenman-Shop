<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($userId <= 0) {
    header("Location: /");
    exit;
}

// Get user information
try {
    $stmt = $conn->prepare("
        SELECT user_id, username, first_name, last_name, email, profile_image, 
               bio, user_type, created_at
        FROM users 
        WHERE user_id = :user_id
    ");
    
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: /");
        exit;
    }
    
    if (empty($user['profile_image'])) {
        $user['profile_image'] = '/assets/images/default-profile.png';
    }
    
    $merchantInfo = null;
    if ($user['user_type'] === 'merchant') {
        $stmtMerchant = $conn->prepare("
            SELECT shop_name, shop_description, shop_logo, shop_banner, 
                   shop_address, shop_phone, shop_email, shop_website
            FROM merchant_profiles 
            WHERE user_id = :user_id
        ");
        
        $stmtMerchant->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtMerchant->execute();
        
        $merchantInfo = $stmtMerchant->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get products if user is a merchant
    $products = [];
    if ($user['user_type'] === 'merchant') {
        $stmtProducts = $conn->prepare("
            SELECT p.product_id, p.name, p.price, p.sale_price, p.main_image, 
                   p.status, AVG(r.rating) as avg_rating
            FROM products p
            LEFT JOIN reviews r ON p.product_id = r.product_id
            WHERE p.user_id = :user_id AND p.status = 'active'
            GROUP BY p.product_id
            ORDER BY p.created_at DESC
            LIMIT 8
        ");
        
        $stmtProducts->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtProducts->execute();
        
        $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    // Log error and show error page
    error_log("User profile error: " . $e->getMessage());
    include '../includes/error.php';
    exit;
}

$pageTitle = $user['first_name'] . ' ' . $user['last_name'] . ' - Profile';
include '../includes/header.php';
?>

<div class="container my-5">
    <div class="row">
        <!-- Profile Header -->
        <div class="col-12">
            <div class="card border-0 rounded-3 shadow-sm overflow-hidden">
                <?php if ($user['user_type'] === 'merchant' && !empty($merchantInfo['shop_banner'])): ?>
                <div class="profile-banner" style="height: 200px; background-image: url('<?php echo htmlspecialchars($merchantInfo['shop_banner']); ?>'); background-size: cover; background-position: center;"></div>
                <?php else: ?>
                <div class="profile-banner bg-primary" style="height: 200px;"></div>
                <?php endif; ?>
                
                <div class="card-body position-relative pt-5 pb-3">
                    <div class="position-absolute" style="top: -50px; left: 20px;">
                        <?php if ($user['user_type'] === 'merchant' && !empty($merchantInfo['shop_logo'])): ?>
                        <img src="<?php echo htmlspecialchars($merchantInfo['shop_logo']); ?>" class="rounded-circle border border-4 border-white" width="100" height="100" alt="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>">
                        <?php else: ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" class="rounded-circle border border-4 border-white" width="100" height="100" alt="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="row align-items-end">
                        <div class="col-md-8">
                            <h2 class="mb-1">
                                <?php if ($user['user_type'] === 'merchant' && !empty($merchantInfo['shop_name'])): ?>
                                <?php echo htmlspecialchars($merchantInfo['shop_name']); ?>
                                <?php else: ?>
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                <?php endif; ?>
                            </h2>
                            <p class="text-muted mb-2">@<?php echo htmlspecialchars($user['username']); ?> 
                                <?php if ($user['user_type'] === 'merchant'): ?>
                                <span class="badge bg-primary ms-2">Merchant</span>
                                <?php elseif ($user['user_type'] === 'admin'): ?>
                                <span class="badge bg-danger ms-2">Admin</span>
                                <?php endif; ?>
                            </p>
                            <p class="mb-3">
                                <?php if ($user['user_type'] === 'merchant' && !empty($merchantInfo['shop_description'])): ?>
                                <?php echo nl2br(htmlspecialchars($merchantInfo['shop_description'])); ?>
                                <?php elseif (!empty($user['bio'])): ?>
                                <?php echo nl2br(htmlspecialchars($user['bio'])); ?>
                                <?php else: ?>
                                <em class="text-muted">No bio available</em>
                                <?php endif; ?>
                            </p>
                            <p class="text-muted small">
                                <i class="bi bi-calendar3"></i> Joined <?php echo date('F Y', strtotime($user['created_at'])); ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $userId): ?>
                            <a href="/chats/index.php?user=<?php echo $userId; ?>" class="btn btn-outline-primary">
                                <i class="bi bi-chat-dots-fill"></i> Message
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($user['user_type'] === 'merchant' && !empty($merchantInfo['shop_website'])): ?>
                            <a href="<?php echo htmlspecialchars($merchantInfo['shop_website']); ?>" class="btn btn-outline-secondary ms-2" target="_blank">
                                <i class="bi bi-globe"></i> Website
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-8 mt-4">
            <?php if ($user['user_type'] === 'merchant' && !empty($products)): ?>
            <!-- Products Section for Merchants -->
            <div class="card border-0 rounded-3 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-4">
                    <h3>Featured Products</h3>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <?php foreach ($products as $product): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm product-card">
                                <a href="/product/details.php?id=<?php echo $product['product_id']; ?>" class="text-decoration-none">
                                    <img src="<?php echo !empty($product['main_image']) ? htmlspecialchars($product['main_image']) : '/assets/images/product-placeholder.jpg'; ?>" 
                                         class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                </a>
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <a href="/product/details.php?id=<?php echo $product['product_id']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h5>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <?php if (!empty($product['sale_price']) && $product['sale_price'] < $product['price']): ?>
                                            <span class="text-danger fw-bold">$<?php echo number_format($product['sale_price'], 2); ?></span>
                                            <span class="text-muted text-decoration-line-through ms-2">$<?php echo number_format($product['price'], 2); ?></span>
                                            <?php else: ?>
                                            <span class="fw-bold">$<?php echo number_format($product['price'], 2); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="rating">
                                            <?php
                                            $avgRating = round($product['avg_rating']);
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $avgRating) {
                                                    echo '<i class="bi bi-star-fill text-warning"></i>';
                                                } else {
                                                    echo '<i class="bi bi-star text-warning"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($products) >= 8): ?>
                    <div class="text-center mt-4">
                        <a href="/product/index.php?merchant=<?php echo $userId; ?>" class="btn btn-outline-primary">
                            View All Products
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Reviews Section -->
            <div class="card border-0 rounded-3 shadow-sm">
                <div class="card-header bg-white border-bottom-0 pt-4">
                    <h3>Reviews</h3>
                </div>
                <div class="card-body">
                    <div id="reviews-container">
                        <!-- Reviews will be loaded via AJAX -->
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading reviews...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-md-4 mt-4">
            <!-- Contact Information -->
            <?php if ($user['user_type'] === 'merchant'): ?>
            <div class="card border-0 rounded-3 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-4">
                    <h3>Contact Information</h3>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <?php if (!empty($merchantInfo['shop_email'])): ?>
                        <li class="mb-3">
                            <i class="bi bi-envelope-fill text-primary me-2"></i>
                            <a href="mailto:<?php echo htmlspecialchars($merchantInfo['shop_email']); ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($merchantInfo['shop_email']); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (!empty($merchantInfo['shop_phone'])): ?>
                        <li class="mb-3">
                            <i class="bi bi-telephone-fill text-primary me-2"></i>
                            <a href="tel:<?php echo htmlspecialchars($merchantInfo['shop_phone']); ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($merchantInfo['shop_phone']); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (!empty($merchantInfo['shop_address'])): ?>
                        <li class="mb-3">
                            <i class="bi bi-geo-alt-fill text-primary me-2"></i>
                            <?php echo htmlspecialchars($merchantInfo['shop_address']); ?>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Related Merchants -->
            <?php if ($user['user_type'] !== 'merchant'): ?>
            <div class="card border-0 rounded-3 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0 pt-4">
                    <h3>Recommended Shops</h3>
                </div>
                <div class="card-body">
                    <div id="recommended-shops">
                        <!-- Shops will be loaded via AJAX -->
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading recommendations...</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Load user reviews
document.addEventListener('DOMContentLoaded', function() {
    // Function to load reviews
    function loadReviews() {
        fetch('/api/get_user_reviews.php?user_id=<?php echo $userId; ?>')
            .then(response => response.json())
            .then(data => {
                const reviewsContainer = document.getElementById('reviews-container');
                
                if (data.success && data.reviews && data.reviews.length > 0) {
                    reviewsContainer.innerHTML = '';
                    
                    data.reviews.forEach(review => {
                        const reviewElement = document.createElement('div');
                        reviewElement.className = 'review-item border-bottom pb-3 mb-3';
                        
                        let reviewStars = '';
                        for (let i = 1; i <= 5; i++) {
                            reviewStars += `<i class="bi bi-star${i <= review.rating ? '-fill' : ''} text-warning"></i>`;
                        }
                        
                        reviewElement.innerHTML = `
                            <div class="d-flex align-items-center mb-2">
                                <img src="${review.reviewer_image}" class="rounded-circle me-2" width="40" height="40" alt="${review.reviewer_name}">
                                <div>
                                    <div class="fw-bold">${review.reviewer_name}</div>
                                    <div class="text-muted small">${review.review_date}</div>
                                </div>
                            </div>
                            <div class="mb-2">${reviewStars}</div>
                            <p class="mb-1">${review.review_text}</p>
                            <div class="small">
                                <a href="/product/details.php?id=${review.product_id}" class="text-decoration-none">
                                    <i class="bi bi-box-seam me-1"></i> ${review.product_name}
                                </a>
                            </div>
                        `;
                        
                        reviewsContainer.appendChild(reviewElement);
                    });
                    
                    // Add load more button if needed
                    if (data.has_more) {
                        const loadMoreBtn = document.createElement('div');
                        loadMoreBtn.className = 'text-center mt-4';
                        loadMoreBtn.innerHTML = `
                            <button class="btn btn-outline-primary" id="load-more-reviews">
                                Load More Reviews
                            </button>
                        `;
                        reviewsContainer.appendChild(loadMoreBtn);
                    }
                } else {
                    reviewsContainer.innerHTML = `
                        <div class="text-center py-5">
                            <i class="bi bi-star text-muted mb-3" style="font-size: 2rem;"></i>
                            <p class="text-muted">No reviews yet</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading reviews:', error);
                document.getElementById('reviews-container').innerHTML = `
                    <div class="text-center py-5">
                        <i class="bi bi-exclamation-circle text-danger mb-3" style="font-size: 2rem;"></i>
                        <p class="text-muted">Failed to load reviews</p>
                    </div>
                `;
            });
    }
    
    // Function to load recommended shops
    function loadRecommendedShops() {
        const recommendedShopsContainer = document.getElementById('recommended-shops');
        if (recommendedShopsContainer) {
            fetch('/api/get_recommended_shops.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.shops && data.shops.length > 0) {
                        recommendedShopsContainer.innerHTML = '';
                        
                        data.shops.forEach(shop => {
                            const shopElement = document.createElement('div');
                            shopElement.className = 'shop-item d-flex align-items-center mb-3';
                            shopElement.innerHTML = `
                                <img src="${shop.logo}" class="rounded-circle me-2" width="50" height="50" alt="${shop.name}">
                                <div>
                                    <div class="fw-bold">${shop.name}</div>
                                    <div class="text-muted small">${shop.product_count} products</div>
                                </div>
                            `;
                            
                            // Make the entire element clickable
                            shopElement.style.cursor = 'pointer';
                            shopElement.addEventListener('click', function() {
                                window.location.href = `/user/profile.php?id=${shop.user_id}`;
                            });
                            
                            recommendedShopsContainer.appendChild(shopElement);
                        });
                    } else {
                        recommendedShopsContainer.innerHTML = `
                            <div class="text-center py-3">
                                <i class="bi bi-shop text-muted mb-3" style="font-size: 2rem;"></i>
                                <p class="text-muted">No shops to recommend</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading recommended shops:', error);
                    recommendedShopsContainer.innerHTML = `
                        <div class="text-center py-3">
                            <i class="bi bi-exclamation-circle text-danger mb-3" style="font-size: 2rem;"></i>
                            <p class="text-muted">Failed to load recommendations</p>
                        </div>
                    `;
                });
        }
    }
    
    // Load data when page loads
    loadReviews();
    loadRecommendedShops();
});
</script>

<?php include '../includes/footer.php'; ?>