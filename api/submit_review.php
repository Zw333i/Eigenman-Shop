<?php
// API endpoint to submit a product review
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once '../config/database.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Get the request data
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['product_id']) || !isset($data['rating']) || !isset($data['review_text'])) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$productId = intval($data['product_id']);
$rating = intval($data['rating']);
$reviewText = trim($data['review_text']);

// Validate rating
if ($rating < 1 || $rating > 5) {
    echo json_encode(['error' => 'Rating must be between 1 and 5']);
    exit;
}

// Validate review text
if (empty($reviewText) || strlen($reviewText) < 5) {
    echo json_encode(['error' => 'Review text is too short']);
    exit;
}

try {
    // Check if product exists
    $stmtProduct = $conn->prepare("
        SELECT product_id, user_id AS merchant_id, name 
        FROM products 
        WHERE product_id = :product_id AND status = 'active'
    ");
    $stmtProduct->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmtProduct->execute();
    
    $product = $stmtProduct->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    // Check if user has purchased this product
    $stmtOrder = $conn->prepare("
        SELECT order_id 
        FROM orders 
        WHERE user_id = :user_id AND product_id = :product_id AND status = 'delivered'
        LIMIT 1
    ");
    $stmtOrder->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmtOrder->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmtOrder->execute();
    
    $hasOrdered = $stmtOrder->rowCount() > 0;
    
    // Check if user has already reviewed this product
    $stmtExistingReview = $conn->prepare("
        SELECT review_id 
        FROM reviews 
        WHERE user_id = :user_id AND product_id = :product_id
        LIMIT 1
    ");
    $stmtExistingReview->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmtExistingReview->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmtExistingReview->execute();
    
    $existingReview = $stmtExistingReview->fetch(PDO::FETCH_ASSOC);
    
    if ($existingReview) {
        // Update existing review
        $stmtUpdate = $conn->prepare("
            UPDATE reviews 
            SET rating = :rating, review_text = :review_text, updated_at = NOW() 
            WHERE review_id = :review_id
        ");
        $stmtUpdate->bindParam(':rating', $rating, PDO::PARAM_INT);
        $stmtUpdate->bindParam(':review_text', $reviewText, PDO::PARAM_STR);
        $stmtUpdate->bindParam(':review_id', $existingReview['review_id'], PDO::PARAM_INT);
        $stmtUpdate->execute();
        
        $reviewId = $existingReview['review_id'];
        $isNew = false;
    } else {
        // Create new review
        $stmtInsert = $conn->prepare("
            INSERT INTO reviews (user_id, product_id, rating, review_text, created_at, updated_at, verified_purchase)
            VALUES (:user_id, :product_id, :rating, :review_text, NOW(), NOW(), :verified_purchase)
        ");
        $stmtInsert->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmtInsert->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmtInsert->bindParam(':rating', $rating, PDO::PARAM_INT);
        $stmtInsert->bindParam(':review_text', $reviewText, PDO::PARAM_STR);
        $stmtInsert->bindParam(':verified_purchase', $hasOrdered, PDO::PARAM_BOOL);
        $stmtInsert->execute();
        
        $reviewId = $conn->lastInsertId();
        $isNew = true;
    }
    
    // If it's a new review, create a notification for the merchant
    if ($isNew && $product['merchant_id'] != $_SESSION['user_id']) {
        // Get user information
        $stmtUser = $conn->prepare("
            SELECT username, CONCAT(first_name, ' ', last_name) AS full_name
            FROM users 
            WHERE user_id = :user_id
        ");
        $stmtUser->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmtUser->execute();
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        // Create notification
        $notificationMessage = $user['full_name'] . " left a " . $rating . "-star review on your product '" . $product['name'] . "'";
        
        $stmtNotification = $conn->prepare("
            INSERT INTO notifications (user_id, sender_id, type, message, product_id, created_at, is_read)
            VALUES (:user_id, :sender_id, 'review', :message, :product_id, NOW(), 0)
        ");
        $stmtNotification->bindParam(':user_id', $product['merchant_id'], PDO::PARAM_INT);
        $stmtNotification->bindParam(':sender_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmtNotification->bindParam(':message', $notificationMessage, PDO::PARAM_STR);
        $stmtNotification->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmtNotification->execute();
    }
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => $isNew ? 'Review submitted successfully' : 'Review updated successfully',
        'review_id' => $reviewId
    ]);
    
} catch (PDOException $e) {
    // Log the error (in a production environment)
    error_log("Submit review error: " . $e->getMessage());
    
    // Return error response
    echo json_encode(['error' => 'Failed to submit review']);
}
?>