<?php
// API endpoint to get user information
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once '../config/database.php';

// Get the requested user ID
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Validate user ID
if ($userId <= 0) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

try {
    // Query to get user information
    $stmt = $conn->prepare("
        SELECT user_id, username, first_name, last_name, email, profile_image, 
               bio, user_type, created_at, last_login
        FROM users 
        WHERE user_id = :user_id
    ");
    
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Set default profile image if none exists
    if (empty($user['profile_image'])) {
        $user['profile_image'] = '/assets/images/default-profile.png';
    }
    
    // Format the user data
    $userData = [
        'id' => $user['user_id'],
        'username' => $user['username'],
        'firstName' => $user['first_name'],
        'lastName' => $user['last_name'],
        'fullName' => $user['first_name'] . ' ' . $user['last_name'],
        'email' => $user['email'],
        'image' => $user['profile_image'],
        'bio' => $user['bio'],
        'type' => $user['user_type'],
        'createdAt' => $user['created_at'],
        'lastLogin' => $user['last_login']
    ];
    
    // If the requested user is a merchant, get additional information
    if ($user['user_type'] === 'merchant') {
        $stmtMerchant = $conn->prepare("
            SELECT shop_name, shop_description, shop_logo, shop_banner, 
                   shop_address, shop_phone, shop_email, shop_website
            FROM merchant_profiles 
            WHERE user_id = :user_id
        ");
        
        $stmtMerchant->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtMerchant->execute();
        
        $merchantData = $stmtMerchant->fetch(PDO::FETCH_ASSOC);
        
        if ($merchantData) {
            $userData['merchantInfo'] = [
                'shopName' => $merchantData['shop_name'],
                'shopDescription' => $merchantData['shop_description'],
                'shopLogo' => $merchantData['shop_logo'] ?: '/assets/images/default-shop-logo.png',
                'shopBanner' => $merchantData['shop_banner'] ?: '/assets/images/default-shop-banner.jpg',
                'shopAddress' => $merchantData['shop_address'],
                'shopPhone' => $merchantData['shop_phone'],
                'shopEmail' => $merchantData['shop_email'],
                'shopWebsite' => $merchantData['shop_website']
            ];
        }
    }
    
    echo json_encode(['success' => true, 'user' => $userData]);
    
} catch (PDOException $e) {
    // Log the error (in a production environment)
    error_log("Get user error: " . $e->getMessage());
    
    // Return error response
    echo json_encode(['error' => 'Failed to get user information']);
}
?>