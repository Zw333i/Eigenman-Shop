<?php
// api/get_new_messages.php
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

// Get the other user ID from the query parameters
if (!isset($_GET['userId']) || empty($_GET['userId'])) {
    echo json_encode(['error' => 'Other user ID is required']);
    exit;
}

$otherUserId = intval($_GET['userId']);

// Get last message ID to fetch only newer messages
$lastMessageId = isset($_GET['lastId']) ? intval($_GET['lastId']) : 0;

try {
    $conn = getConnection();
    
    // Fetch only new messages
    $query = "
        SELECT c.*, 
               CASE WHEN c.senderId = ? THEN 1 ELSE 0 END as is_sender,
               u_sender.username as sender_username,
               u_sender.profilePicture as sender_picture
        FROM chats c
        JOIN user u_sender ON c.senderId = u_sender.userId
        WHERE ((c.senderId = ? AND c.receiverId = ?) OR 
               (c.senderId = ? AND c.receiverId = ?))
            AND c.chatId > ?
        ORDER BY c.timestamp ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiiii", $currentUserId, $currentUserId, $otherUserId, $otherUserId, $currentUserId, $lastMessageId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        // Format the timestamp
        $row['formatted_time'] = formatDateTime($row['timestamp']);
        $messages[] = $row;
    }
    
    // Mark messages as read (only those sent by the other user)
    $updateQuery = "
        UPDATE chats
        SET readStatus = 1
        WHERE senderId = ? AND receiverId = ? AND readStatus = 0
    ";
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ii", $otherUserId, $currentUserId);
    $updateStmt->execute();
    
    $conn->close();
    
    // Return the new messages
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}