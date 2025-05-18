<?php
// api/get_messages.php
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

// Optional parameter for pagination
$lastMessageId = isset($_GET['lastId']) ? intval($_GET['lastId']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

try {
    $conn = getConnection();
    
    // Fetch messages between the two users
    $query = "
        SELECT c.*, 
               CASE WHEN c.senderId = ? THEN 1 ELSE 0 END as is_sender,
               u_sender.username as sender_username,
               u_sender.profilePicture as sender_picture
        FROM chats c
        JOIN user u_sender ON c.senderId = u_sender.userId
        WHERE ((c.senderId = ? AND c.receiverId = ?) OR 
               (c.senderId = ? AND c.receiverId = ?))
    ";
    
    // Add lastId condition for pagination if provided
    if ($lastMessageId > 0) {
        $query .= " AND c.chatId < ?";
    }
    
    $query .= " ORDER BY c.timestamp DESC LIMIT ?";
    
    $stmt = $conn->prepare($query);
    
    if ($lastMessageId > 0) {
        $stmt->bind_param("iiiiiii", $currentUserId, $currentUserId, $otherUserId, $otherUserId, $currentUserId, $lastMessageId, $limit);
    } else {
        $stmt->bind_param("iiiiii", $currentUserId, $currentUserId, $otherUserId, $otherUserId, $currentUserId, $limit);
    }
    
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
    
    // Reverse messages to get chronological order (oldest first)
    $messages = array_reverse($messages);
    
    // Get user info for the chat
    $otherUser = getUserById($otherUserId);
    $userInfo = [
        'userId' => $otherUser['userId'],
        'username' => $otherUser['username'],
        'firstname' => $otherUser['firstname'],
        'lastname' => $otherUser['lastname'],
        'profilePicture' => $otherUser['profilePicture'] ?? 'assets/images/profile-placeholder.jpg'
    ];
    
    $conn->close();
    
    // Return the messages along with user info
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'user' => $userInfo,
        'hasMore' => count($messages) >= $limit
    ]);
}
catch(Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}