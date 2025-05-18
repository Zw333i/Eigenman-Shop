<?php
// API endpoint for searching users
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once '../config/database.php';

// Get the search query
$searchQuery = isset($_GET['query']) ? trim($_GET['query']) : '';

// Validate search query
if (empty($searchQuery) || strlen($searchQuery) < 2) {
    echo json_encode(['error' => 'Search query too short']);
    exit;
}

try {
    // Prepare search query - look for matches in name, email, or username
    $stmt = $conn->prepare("
        SELECT user_id, username, CONCAT(first_name, ' ', last_name) AS full_name, 
               profile_image, user_type 
        FROM users 
        WHERE (username LIKE :query 
           OR first_name LIKE :query 
           OR last_name LIKE :query 
           OR email LIKE :query)
          AND user_id != :current_user_id
        LIMIT 20
    ");
    
    $searchParam = "%{$searchQuery}%";
    $stmt->bindParam(':query', $searchParam, PDO::PARAM_STR);
    $stmt->bindParam(':current_user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the results
    $formattedResults = [];
    foreach ($users as $user) {
        // Set default profile image if none exists
        if (empty($user['profile_image'])) {
            $user['profile_image'] = '/assets/images/default-profile.png';
        }
        
        $formattedResults[] = [
            'id' => $user['user_id'],
            'username' => $user['username'],
            'name' => $user['full_name'],
            'image' => $user['profile_image'],
            'type' => $user['user_type']
        ];
    }
    
    echo json_encode(['success' => true, 'users' => $formattedResults]);
    
} catch (PDOException $e) {
    // Log the error (in a production environment)
    error_log("Search users error: " . $e->getMessage());
    
    // Return error response
    echo json_encode(['error' => 'Failed to search users']);
}
?>