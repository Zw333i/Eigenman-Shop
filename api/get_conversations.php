<?php
// api/get_conversations.php
header('Content-Type: application/json');
require_once '../includes/functions.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// Get the current user ID
$currentUserId = $_SESSION['userId'];

try {
    // Get conversations
    $conversations = getUserConversations($currentUserId);
    
    // Format timestamps
    foreach ($conversations as &$conversation) {
        if (isset($conversation['last_message_time'])) {
            $conversation['formatted_time'] = formatTimestamp($conversation['last_message_time']);
        }
    }
    
    // Return the conversations
    echo json_encode([
        'success' => true,
        'conversations' => $conversations
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}