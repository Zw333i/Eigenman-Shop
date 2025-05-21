<?php
// API endpoint to check if a product is in user's favorites
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => true, 'is_favorite' => false]);
    exit;
}

// Include database connection
require_once '../config/database.php';

$productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

// Validate product ID
if ($productId <= 0) {
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

try {
    // Check if the product is in user's favorites
    $stmt = $conn->prepare("
        SELECT id FROM favorites 
        WHERE user_id = :user_id AND product_id = :product_id
    ");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmt->execute();
    
    $isFavorite = $stmt->rowCount() > 0;
    
    echo json_encode(['success' => true, 'is_favorite' => $isFavorite]);
    
} catch (PDOException $e) {
    // Log the error (in a production environment)
    error_log("Check favorite error: " . $e->getMessage());
    
    // Return error response
    echo json_encode(['error' => 'Failed to check favorite status']);
}
?>