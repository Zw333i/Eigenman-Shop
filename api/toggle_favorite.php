<?php
// API endpoint to toggle a product as favorite
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
if (!isset($data['product_id'])) {
    echo json_encode(['error' => 'Missing product ID']);
    exit;
}

$productId = intval($data['product_id']);
$userId = $_SESSION['user_id'];

try {
    // Check if product exists
    $stmtProduct = $conn->prepare("SELECT product_id FROM products WHERE product_id = :product_id");
    $stmtProduct->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmtProduct->execute();
    
    if ($stmtProduct->rowCount() === 0) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    
    // Check if favorite already exists
    $stmtCheck = $conn->prepare("
        SELECT id FROM favorites 
        WHERE user_id = :user_id AND product_id = :product_id
    ");
    $stmtCheck->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmtCheck->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmtCheck->execute();
    
    $isFavorite = $stmtCheck->rowCount() > 0;
    
    if ($isFavorite) {
        // Remove from favorites
        $stmtDelete = $conn->prepare("
            DELETE FROM favorites 
            WHERE user_id = :user_id AND product_id = :product_id
        ");
        $stmtDelete->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtDelete->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmtDelete->execute();
        
        echo json_encode([
            'success' => true, 
            'is_favorite' => false,
            'message' => 'Product removed from favorites'
        ]);
    } else {
        // Add to favorites
        $stmtInsert = $conn->prepare("
            INSERT INTO favorites (user_id, product_id, created_at)
            VALUES (:user_id, :product_id, NOW())
        ");
        $stmtInsert->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtInsert->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmtInsert->execute();
        
        // Get product and merchant information
        $stmtProductInfo = $conn->prepare("
            SELECT p.name as product_name, p.user_id as merchant_id, u.username as merchant_username
            FROM products p
            JOIN users u ON p.user_id = u.user_id
            WHERE p.product_id = :product_id
        ");
        $stmtProductInfo->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmtProductInfo->execute();
        $productInfo = $stmtProductInfo->fetch(PDO::FETCH_ASSOC);
        
        // Create notification for the merchant
        if ($productInfo && $productInfo['merchant_id'] != $userId) {
            // Get user information
            $stmtUser = $conn->prepare("
                SELECT username, CONCAT(first_name, ' ', last_name) AS full_name
                FROM users 
                WHERE user_id = :user_id
            ");
            $stmtUser->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmtUser->execute();
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            
            $notificationMessage = $user['full_name'] . " added your product '" . $productInfo['product_name'] . "' to favorites";
            
            $stmtNotification = $conn->prepare("
                INSERT INTO notifications (user_id, sender_id, type, message, product_id, created_at, is_read)
                VALUES (:user_id, :sender_id, 'favorite', :message, :product_id, NOW(), 0)
            ");
            $stmtNotification->bindParam(':user_id', $productInfo['merchant_id'], PDO::PARAM_INT);
            $stmtNotification->bindParam(':sender_id', $userId, PDO::PARAM_INT);
            $stmtNotification->bindParam(':message', $notificationMessage, PDO::PARAM_STR);
            $stmtNotification->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmtNotification->execute();
        }
        
        echo json_encode([
            'success' => true, 
            'is_favorite' => true,
            'message' => 'Product added to favorites'
        ]);
    }
    
} catch (PDOException $e) {
    // Log the error (in a production environment)
    error_log("Toggle favorite error: " . $e->getMessage());
    
    // Return error response
    echo json_encode(['error' => 'Failed to update favorites']);
}
?>