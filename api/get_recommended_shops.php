<?php
// API endpoint to get recommended shops
header('Content-Type: application/json');
session_start();

// Include database connection
require_once '../config/database.php';

// Get current user ID if logged in
$currentUserId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;

try {
    // Get popular merchants based on product ratings and number of products
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, CONCAT(u.first_name, ' ', u.last_name) AS full_name,
               m.shop_name, m.shop_logo, COUNT(p.product_id) AS product_count,
               AVG(IFNULL(r.rating, 0)) AS avg_rating
        FROM users u
        JOIN merchant_profiles m ON u.user_id = m.user_id
        LEFT JOIN products p ON u.user_id = p.user_id AND p.status = 'active'
        LEFT JOIN reviews r ON p.product_id = r.product_id
        WHERE u.user_type = 'merchant'
        GROUP BY u.user_id
        ORDER BY avg_rating DESC, product_count DESC
        LIMIT :limit
    ");
    
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $shops = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format shops data
    $formattedShops = [];
    foreach ($shops as $shop) {
        $shopName = !empty($shop['shop_name']) ? $shop['shop_name'] : $shop['full_name'] . "'s Shop";
        $shopLogo = !empty($shop['shop_logo']) ? $shop['shop_logo'] : '/assets/images/default-shop-logo.png';
        
        $formattedShops[] = [
            'user_id' => $shop['user_id'],
            'username' => $shop['username'],
            'name' => $shopName,
            'logo' => $shopLogo,
            'product_count' => $shop['product_count'],
            'avg_rating' => round($shop['avg_rating'], 1)
        ];
    }
    
    echo json_encode(['success' => true, 'shops' => $formattedShops]);
    
} catch (PDOException $e) {
    // Log the error (in a production environment)
    error_log("Get recommended shops error: " . $e->getMessage());
    
    // Return error response
    echo json_encode(['error' => 'Failed to get recommended shops']);
}
?>