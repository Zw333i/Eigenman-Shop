<?php
require_once '../includes/functions.php';

// Only allow AJAX POST requests
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$receiverId = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';

// Validate input
if (empty($message) || $receiverId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Check if receiver exists
$receiver = getUserById($receiverId);
if (!$receiver) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Recipient not found']);
    exit;
}

// Send message
$userId = $_SESSION['user_id'];
$messageId = sendChatMessage($userId, $receiverId, $message);

if ($messageId) {
    // Get current timestamp for display
    $timestamp = date('Y-m-d H:i:s');
    
    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'timestamp' => $timestamp,
        'formatted_time' => date('M d, g:i A', strtotime($timestamp))
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}