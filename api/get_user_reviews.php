<?php
// API endpoint to get reviews for a user
header('Content-Type: application/json');
session_start();

// Include database connection
require_once '../config/database.php';

// Get the requested user ID
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 5; // Number of reviews per page

// Validate user ID
if ($userId <= 0) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

try {
    // Check if the user exists and get their role
    $stmtUser = $conn->prepare("SELECT user_type FROM users WHERE user_id = :user_id");
    $stmtUser->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmtUser->execute();
    
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $reviews = [];
    $totalReviews = 0;
    $offset = ($page - 1) * $perPage;
    
    if ($user['user_type'] === 'merchant') {
        // Get reviews for products sold by this merchant
        $stmtCount = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM reviews r
            JOIN products p ON r.product_id = p.product_id
            WHERE p.user_id = :user_id
        ");
        $stmtCount->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtCount->execute();
        $totalReviews = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get reviews with pagination
        $stmtReviews = $conn->prepare("
            SELECT r.review_id, r.product_id, r.user_id as reviewer_id, r.rating, r.review_text, 
                   r.created_at, p.name as product_name, 
                   u.username as reviewer_username, u.first_name as reviewer_first_name, 
                   u.last_name as reviewer_last_name, u.profile_image as reviewer_image
            FROM reviews r
            JOIN products p ON r.product_id = p.product_id
            JOIN users u ON r.user_id = u.user_id
            WHERE p.user_id = :user_id
            ORDER BY r.created_at DESC
            LIMIT :offset, :per_page
        ");
        $stmtReviews->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtReviews->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmtReviews->bindParam(':per_page', $perPage, PDO::PARAM_INT);
        $stmtReviews->execute();
        
        $reviewsData = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Get reviews written by this user
        $stmtCount = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM reviews
            WHERE user_id = :user_id
        ");
        $stmtCount->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtCount->execute();
        $totalReviews = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get reviews with pagination
        $stmtReviews = $conn->prepare("
            SELECT r.review_id, r.product_id, r.rating, r.review_text, r.created_at,
                   p.name as product_name, p.main_image as product_image,
                   u.user_id as merchant_id, u.username as merchant_username,
                   u.first_name as merchant_first_name, u.last_name as merchant_last_name
            FROM reviews r
            JOIN products p ON r.product_id = p.product_id
            JOIN users u ON p.user_id = u.user_id
            WHERE r.user_id = :user_id
            ORDER BY r.created_at DESC
            LIMIT :offset, :per_page
        ");
        $stmtReviews->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtReviews->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmtReviews->bindParam(':per_page', $perPage, PDO::PARAM_INT);
        $stmtReviews->execute();
        
        $reviewsData = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Format reviews data
    foreach ($reviewsData as $review) {
        $formattedReview = [
            'review_id' => $review['review_id'],
            'product_id' => $review['product_id'],
            'product_name' => $review['product_name'],
            'rating' => $review['rating'],
            'review_text' => $review['review_text'],
            'review_date' => date('M d, Y', strtotime($review['created_at']))
        ];
        
        if ($user['user_type'] === 'merchant') {
            // For merchant profiles, show customer reviews on their products
            $formattedReview['reviewer_id'] = $review['reviewer_id'];
            $formattedReview['reviewer_name'] = $review['reviewer_first_name'] . ' ' . $review['reviewer_last_name'];
            $formattedReview['reviewer_username'] = $review['reviewer_username'];
            $formattedReview['reviewer_image'] = !empty($review['reviewer_image']) ? 
                $review['reviewer_image'] : '/assets/images/default-profile.png';
        } else {
            // For customer profiles, show their reviews on products
            $formattedReview['merchant_id'] = $review['merchant_id'];
            $formattedReview['merchant_name'] = $review['merchant_first_name'] . ' ' . $review['merchant_last_name'];
            $formattedReview['merchant_username'] = $review['merchant_username'];
            $formattedReview['product_image'] = !empty($review['product_image']) ? 
                $review['product_image'] : '/assets/images/product-placeholder.jpg';
        }
        
        $reviews[] = $formattedReview;
    }
    
    // Calculate if there are more pages
    $hasMore = ($page * $perPage) < $totalReviews;
    
    echo json_encode([
        'success' => true, 
        'reviews' => $reviews,
        'total' => $totalReviews,
        'page' => $page,
        'per_page' => $perPage,
        'has_more' => $hasMore
    ]);
    
} catch (PDOException $e) {
    // Log the error (in a production environment)
    error_log("Get user reviews error: " . $e->getMessage());
    
    // Return error response
    echo json_encode(['error' => 'Failed to get user reviews']);
}
?>