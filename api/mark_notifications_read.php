<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['userId'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Include database configuration
require_once '../config/database.php';

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$userId = isset($data['userId']) ? $data['userId'] : $_SESSION['userId'];

// Security check - only allow marking notifications for the logged-in user
if ($userId != $_SESSION['userId']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized action']);
    exit();
}

// Update notifications
try {
    $stmt = $conn->prepare("UPDATE Notifications SET isRead = 1 WHERE receiverId = ?");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}