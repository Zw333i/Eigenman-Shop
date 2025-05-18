<?php
// includes/functions.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function getConnection() {
    require_once __DIR__ . '/../config/database.php';
    
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

function getPDOConnection() {
    require_once __DIR__ . '/../config/database.php';
    
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function fetchAllFromMysqli($stmt) {
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function executeQuery($sql, $params = [], $types = '') {
    $conn = getConnection();
    
    if ($stmt = $conn->prepare($sql)) {
        if (!empty($params)) {
            if (empty($types)) {
                $types = '';
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_double($param)) {
                        $types .= 'd';
                    } elseif (is_string($param)) {
                        $types .= 's';
                    } else {
                        $types .= 'b';
                    }
                }
            }
            
            // Bind parameters dynamically
            if ($params) {
                $bindParams = array();
                $bindParams[] = &$types;
                
                for ($i = 0; $i < count($params); $i++) {
                    $bindParams[] = &$params[$i];
                }
                
                call_user_func_array(array($stmt, 'bind_param'), $bindParams);
            }
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
            return $rows;
        } else {
            $success = ($stmt->affected_rows > 0);
            $insertId = $stmt->insert_id;
            $stmt->close();
            return [
                'success' => $success,
                'insert_id' => $insertId,
                'affected_rows' => $stmt->affected_rows
            ];
        }
    }
    
    return false;
}

// Format price to currency
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

// Get user data based on session
function getCurrentUser() {
    if (!isset($_SESSION['userId'])) {
        return null;
    }
    
    $conn = getConnection();
    $user_id = $_SESSION['userId'];
    
    $stmt = $conn->prepare("SELECT * FROM user WHERE userId = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $conn->close();
        return $user;
    }
    
    $conn->close();
    return null;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['userId']);
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /e-commerce/auth/login.php");
        exit;
    }
}

// Get cart count for current user
function getCartCount() {
    if (!isLoggedIn()) {
        return 0;
    }
    
    $conn = getConnection();
    $user_id = $_SESSION['userId'];
    
    $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM userCart WHERE userId = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $count = $row['count'] ? $row['count'] : 0;
        $conn->close();
        return $count;
    }
    
    $conn->close();
    return 0;
}

// Get products by category
function getProductsByCategory($category_id) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM item WHERE categoryId = ? AND active = 1");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    $conn->close();
    return $products;
}

// Get featured products
function getFeaturedProducts($limit = 8) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM item WHERE featured = 1 AND quantity > 0 LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    $conn->close();
    return $products;
}

// Get all categories
function getAllCategories() {
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM categories WHERE active = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    $conn->close();
    return $categories;
}

// Generate order number
function generateOrderNumber() {
    return 'ORD-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}

// Validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get merchant ID from user ID
function getMerchantIdFromUserId($conn, $userId) {
    $stmt = $conn->prepare("SELECT merchantId FROM merchant WHERE userId = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $merchant = $result->fetch_assoc();
        return $merchant['merchantId'];
    }
    
    return null;
}

// Get store name from merchant ID
function getStoreNameByMerchantId($merchantId) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT storeName FROM merchant WHERE merchantId = ?");
    $stmt->bind_param("i", $merchantId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $merchant = $result->fetch_assoc();
        $conn->close();
        return $merchant['storeName'];
    }
    
    $conn->close();
    return "Unknown Store";
}

// Get user ID from merchant ID
function getUserIdFromMerchantId($merchantId) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT userId FROM merchant WHERE merchantId = ?");
    $stmt->bind_param("i", $merchantId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $merchant = $result->fetch_assoc();
        $conn->close();
        return $merchant['userId'];
    }
    
    $conn->close();
    return null;
}

// Get user info by ID
function getUserById($userId) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM user WHERE userId = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $conn->close();
        return $user;
    }
    
    $conn->close();
    return null;
}

// Search users with PDO (using fetchAll and bindParam functionality)
function searchUsers($searchTerm) {
    $pdo = getPDOConnection();
    
    $searchParam = '%' . $searchTerm . '%';
    $sql = "SELECT * FROM user WHERE username LIKE :query OR firstname LIKE :query OR lastname LIKE :query";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':query', $searchParam, PDO::PARAM_STR);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $users;
}

// Check if user is merchant
function isMerchant($userId = null) {
    if ($userId === null) {
        if (!isLoggedIn()) {
            return false;
        }
        $userId = $_SESSION['userId'];
    }
    
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT role FROM user WHERE userId = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $conn->close();
        return ($user['role'] == 'merchant' || $user['role'] == 'admin');
    }
    
    $conn->close();
    return false;
}

// Get notification count (unread notifications)
function getUnreadNotificationCount($userId) {
    if (!isLoggedIn()) {
        return 0;
    }
    
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE receiverId = ? AND readStatus = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $conn->close();
        return $row['count'];
    }
    
    $conn->close();
    return 0;
}

// Get unread messages count
function getUnreadMessagesCount($userId) {
    if (!isLoggedIn()) {
        return 0;
    }
    
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chats WHERE receiverId = ? AND readStatus = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $conn->close();
        return $row['count'];
    }
    
    $conn->close();
    return 0;
}

// Format date
function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('M d, Y');
}

// Format datetime
function formatDateTime($dateString) {
    $date = new DateTime($dateString);
    return $date->format('M d, Y H:i');
}

// Format timestamp for notifications and messages
function formatTimestamp($timestamp) {
    $dateTime = new DateTime($timestamp);
    $now = new DateTime();
    $diff = $now->getTimestamp() - $dateTime->getTimestamp();
    
    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " " . ($minutes == 1 ? "minute" : "minutes") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " " . ($hours == 1 ? "hour" : "hours") . " ago";
    } elseif ($diff < 172800) { // Less than 2 days
        return "Yesterday at " . $dateTime->format('h:i A');
    } elseif ($diff < 604800) { // Less than a week
        return $dateTime->format('l') . " at " . $dateTime->format('h:i A');
    } else {
        return $dateTime->format('M d') . " at " . $dateTime->format('h:i A');
    }
}

// Check if a product belongs to the merchant
function isProductOwnedByMerchant($itemId, $merchantId) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM item WHERE itemId = ? AND merchantId = ?");
    $stmt->bind_param("ii", $itemId, $merchantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $conn->close();
    return ($row['count'] > 0);
}

// Create a notification
function createNotification($receiverId, $senderId, $message, $orderId = null) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("INSERT INTO notifications (receiverId, senderId, orderId, message, timestamp, readStatus) VALUES (?, ?, ?, ?, NOW(), 0)");
    $stmt->bind_param("iiis", $receiverId, $senderId, $orderId, $message);
    $result = $stmt->execute();
    
    $conn->close();
    return $result;
}

// Get chat conversations for a user
function getUserConversations($userId) {
    $conn = getConnection();
    
    // Get unique conversations (both as sender and receiver)
    $query = "
        SELECT 
            DISTINCT CASE 
                WHEN c.senderId = ? THEN c.receiverId 
                ELSE c.senderId 
            END as other_user_id,
            (SELECT COUNT(*) FROM chats WHERE 
                receiverId = ? AND 
                senderId = CASE WHEN c.senderId = ? THEN c.receiverId ELSE c.senderId END AND
                readStatus = 0) as unread_count,
            (SELECT MAX(timestamp) FROM chats WHERE 
                (senderId = ? AND receiverId = CASE WHEN c.senderId = ? THEN c.receiverId ELSE c.senderId END) OR
                (receiverId = ? AND senderId = CASE WHEN c.senderId = ? THEN c.receiverId ELSE c.senderId END)
            ) as last_message_time
        FROM chats c
        WHERE c.senderId = ? OR c.receiverId = ?
        ORDER BY last_message_time DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiiiiiii", $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $otherUser = getUserById($row['other_user_id']);
        if ($otherUser) {
            $conversations[] = [
                'userId' => $row['other_user_id'],
                'username' => $otherUser['username'],
                'firstname' => $otherUser['firstname'],
                'lastname' => $otherUser['lastname'],
                'profilePicture' => $otherUser['profilePicture'],
                'unread_count' => $row['unread_count'],
                'last_message_time' => $row['last_message_time']
            ];
        }
    }
    
    $conn->close();
    return $conversations;
}

// Get chat messages between two users
function getChatMessages($userId, $otherUserId) {
    $conn = getConnection();
    
    $query = "
        SELECT c.*, 
               CASE WHEN c.senderId = ? THEN 1 ELSE 0 END as is_sender
        FROM chats c
        WHERE (c.senderId = ? AND c.receiverId = ?) OR 
              (c.senderId = ? AND c.receiverId = ?)
        ORDER BY c.timestamp ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiii", $userId, $userId, $otherUserId, $otherUserId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    // Mark messages as read
    $updateQuery = "
        UPDATE chats
        SET readStatus = 1
        WHERE senderId = ? AND receiverId = ? AND readStatus = 0
    ";
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ii", $otherUserId, $userId);
    $updateStmt->execute();
    
    $conn->close();
    return $messages;
}

// Send a chat message
function sendChatMessage($senderId, $receiverId, $message) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("INSERT INTO chats (senderId, receiverId, message, timestamp, readStatus) VALUES (?, ?, ?, NOW(), 0)");
    $stmt->bind_param("iis", $senderId, $receiverId, $message);
    $result = $stmt->execute();
    $messageId = $conn->insert_id;
    
    $conn->close();
    return $messageId;
}

// Get all notifications for a user
function getUserNotifications($userId, $limit = 10) {
    $conn = getConnection();
    
    $query = "
        SELECT n.*, u.username, u.firstname, u.lastname, u.profilePicture 
        FROM notifications n
        LEFT JOIN user u ON n.senderId = u.userId
        WHERE n.receiverId = ?
        ORDER BY n.timestamp DESC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $conn->close();
    return $notifications;
}

// Mark notifications as read
function markNotificationsAsRead($userId) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("UPDATE notifications SET readStatus = 1 WHERE receiverId = ? AND readStatus = 0");
    $stmt->bind_param("i", $userId);
    $result = $stmt->execute();
    
    $conn->close();
    return $result;
}

// Get item details by ID
function getItemById($itemId) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM item WHERE itemId = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $item = $result->fetch_assoc();
        $conn->close();
        return $item;
    }
    
    $conn->close();
    return null;
}

// Search items by keyword
function searchItems($keyword) {
    $conn = getConnection();
    $keyword = "%$keyword%";
    
    $stmt = $conn->prepare("SELECT * FROM item WHERE (itemName LIKE ? OR brand LIKE ?) AND quantity > 0");
    $stmt->bind_param("ss", $keyword, $keyword);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    $conn->close();
    return $items;
}

// Search merchants by keyword
function searchMerchants($keyword) {
    $conn = getConnection();
    $keyword = "%$keyword%";
    
    $stmt = $conn->prepare("
        SELECT m.*, u.username, u.firstname, u.lastname, u.profilePicture, u.followers 
        FROM merchant m
        JOIN user u ON m.userId = u.userId
        WHERE m.storeName LIKE ? OR u.username LIKE ?
    ");
    $stmt->bind_param("ss", $keyword, $keyword);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $merchants = [];
    while ($row = $result->fetch_assoc()) {
        $merchants[] = $row;
    }
    
    $conn->close();
    return $merchants;
}

// Save search history
function saveSearchHistory($userId, $keyword, $itemId = null, $merchantId = null, $searchType = null) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("INSERT INTO search (userId, keyword, itemId, merchantId, searchType) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isiss", $userId, $keyword, $itemId, $merchantId, $searchType);
    $result = $stmt->execute();
    
    $conn->close();
    return $result;
}

// Get user's search history
function getUserSearchHistory($userId, $limit = 10) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("
        SELECT * FROM search 
        WHERE userId = ? 
        ORDER BY searchId DESC 
        LIMIT ?
    ");
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    $conn->close();
    return $history;
}

// Get merchant's orders
function getMerchantOrders($merchantId, $status = null) {
    $conn = getConnection();
    
    $query = "
        SELECT o.*, u.username, u.firstname, u.lastname, i.itemName, i.picture
        FROM orders o
        JOIN user u ON o.userId = u.userId
        JOIN item i ON o.itemId = i.itemId
        WHERE o.merchantId = ?
    ";
    
    if ($status === 'toPay') {
        $query .= " AND o.toPay = 1";
    } elseif ($status === 'toShip') {
        $query .= " AND o.toPay = 0 AND o.toShip = 1";
    } elseif ($status === 'toReceive') {
        $query .= " AND o.toShip = 0 AND o.toReceive = 1";
    } elseif ($status === 'toRate') {
        $query .= " AND o.toReceive = 0 AND o.toRate = 1";
    } elseif ($status === 'completed') {
        $query .= " AND o.toRate = 0";
    }
    
    $query .= " ORDER BY o.orderDate DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $merchantId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    
    $conn->close();
    return $orders;
}

// Get user's orders
function getUserOrders($userId, $status = null) {
    $conn = getConnection();
    
    $query = "
        SELECT o.*, i.itemName, i.picture, m.storeName
        FROM orders o
        JOIN item i ON o.itemId = i.itemId
        JOIN merchant m ON o.merchantId = m.merchantId
        WHERE o.userId = ?
    ";
    
    if ($status === 'toPay') {
        $query .= " AND o.toPay = 1";
    } elseif ($status === 'toShip') {
        $query .= " AND o.toPay = 0 AND o.toShip = 1";
    } elseif ($status === 'toReceive') {
        $query .= " AND o.toShip = 0 AND o.toReceive = 1";
    } elseif ($status === 'toRate') {
        $query .= " AND o.toReceive = 0 AND o.toRate = 1";
    } elseif ($status === 'completed') {
        $query .= " AND o.toRate = 0";
    }
    
    $query .= " ORDER BY o.orderDate DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    
    $conn->close();
    return $orders;
}

// Get order details by ID
function getOrderById($orderId) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("
        SELECT o.*, i.itemName, i.picture, i.brand, i.itemPrice, 
               u.firstname as customer_firstname, u.lastname as customer_lastname, 
               u.contactNum as customer_contact,
               m.storeName, m.userId as merchant_userId
        FROM orders o
        JOIN item i ON o.itemId = i.itemId
        JOIN user u ON o.userId = u.userId
        JOIN merchant m ON o.merchantId = m.merchantId
        WHERE o.orderId = ?
    ");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        $conn->close();
        return $order;
    }
    
    $conn->close();
    return null;
}

// Update order status
function updateOrderStatus($orderId, $status) {
    $conn = getConnection();
    
    $updates = [];
    if ($status === 'paid') {
        $updates = ["toPay = 0", "toShip = 1"];
    } elseif ($status === 'shipped') {
        $updates = ["toShip = 0", "toReceive = 1"];
    } elseif ($status === 'received') {
        $updates = ["toReceive = 0", "toRate = 1"];
    } elseif ($status === 'rated') {
        $updates = ["toRate = 0"];
    }
    
    if (empty($updates)) {
        $conn->close();
        return false;
    }
    
    $updateStr = implode(", ", $updates);
    $query = "UPDATE orders SET $updateStr WHERE orderId = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $orderId);
    $result = $stmt->execute();
    
    $conn->close();
    return $result;
}

// Get total sales for a merchant (for analytics)
function getMerchantTotalSales($merchantId) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("
        SELECT SUM(totalPrice) as total 
        FROM orders 
        WHERE merchantId = ? AND toPay = 0
    ");
    $stmt->bind_param("i", $merchantId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $conn->close();
        return $row['total'] ? $row['total'] : 0;
    }
    
    $conn->close();
    return 0;
}

// Get monthly sales for a merchant (for analytics)
function getMerchantMonthlySales($merchantId, $year = null) {
    if ($year === null) {
        $year = date('Y');
    }
    
    $conn = getConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            MONTH(orderDate) as month, 
            SUM(totalPrice) as total 
        FROM orders 
        WHERE merchantId = ? AND YEAR(orderDate) = ? AND toPay = 0
        GROUP BY MONTH(orderDate)
    ");
    $stmt->bind_param("ii", $merchantId, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $monthlySales = array_fill(1, 12, 0); // Initialize all months with 0
    
    while ($row = $result->fetch_assoc()) {
        $monthlySales[$row['month']] = $row['total'];
    }
    
    $conn->close();
    return $monthlySales;
}

// Get top selling products for a merchant
function getMerchantTopProducts($merchantId, $limit = 5) {
    $conn = getConnection();
    
    $stmt = $conn->prepare("
        SELECT i.itemId, i.itemName, i.picture, SUM(o.quantity) as total_sold
        FROM orders o
        JOIN item i ON o.itemId = i.itemId
        WHERE o.merchantId = ? AND o.toPay = 0
        GROUP BY i.itemId, i.itemName
        ORDER BY total_sold DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $merchantId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    $conn->close();
    return $products;
}

// Search products with PDO (using bindParam and fetchAll)
function searchProductsPDO($searchTerm, $categoryId = null) {
    $pdo = getPDOConnection();
    
    $searchParam = '%' . $searchTerm . '%';
    
    if ($categoryId) {
        $sql = "SELECT * FROM item WHERE (itemName LIKE :query OR brand LIKE :query) AND categoryId = :categoryId AND quantity > 0";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':query', $searchParam, PDO::PARAM_STR);
        $stmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);
    } else {
        $sql = "SELECT * FROM item WHERE (itemName LIKE :query OR brand LIKE :query) AND quantity > 0";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':query', $searchParam, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $products;
}

// Advanced search with multiple filters using PDO
function advancedProductSearch($filters) {
    $pdo = getPDOConnection();
    
    $conditions = [];
    $params = [];
    
    if (!empty($filters['keyword'])) {
        $conditions[] = "(itemName LIKE :keyword OR brand LIKE :keyword OR description LIKE :keyword)";
        $params[':keyword'] = '%' . $filters['keyword'] . '%';
    }
    
    if (!empty($filters['category'])) {
        $conditions[] = "categoryId = :categoryId";
        $params[':categoryId'] = $filters['category'];
    }
    
    if (!empty($filters['minPrice'])) {
        $conditions[] = "itemPrice >= :minPrice";
        $params[':minPrice'] = $filters['minPrice'];
    }
    
    if (!empty($filters['maxPrice'])) {
        $conditions[] = "itemPrice <= :maxPrice";
        $params[':maxPrice'] = $filters['maxPrice'];
    }
    
    if (!empty($filters['brand'])) {
        $conditions[] = "brand = :brand";
        $params[':brand'] = $filters['brand'];
    }
    
    // Always show in-stock items
    $conditions[] = "quantity > 0";
    
    $sql = "SELECT * FROM item";
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    // Add sorting
    if (!empty($filters['sort'])) {
        switch ($filters['sort']) {
            case 'price_asc':
                $sql .= " ORDER BY itemPrice ASC";
                break;
            case 'price_desc':
                $sql .= " ORDER BY itemPrice DESC";
                break;
            case 'newest':
                $sql .= " ORDER BY dateAdded DESC";
                break;
            case 'popularity':
                $sql .= " ORDER BY salesCount DESC";
                break;
            default:
                $sql .= " ORDER BY itemId DESC";
        }
    } else {
        $sql .= " ORDER BY itemId DESC";
    }
    
    $stmt = $pdo->prepare($sql);
    
    // Bind all parameters
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindParam($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindParam($key, $value, PDO::PARAM_STR);
        }
    }
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $products;
}

// Get related products using PDO
function getRelatedProducts($itemId, $categoryId, $limit = 4) {
    $pdo = getPDOConnection();
    
    $sql = "SELECT * FROM item WHERE categoryId = :categoryId AND itemId != :itemId AND quantity > 0 ORDER BY RAND() LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);
    $stmt->bindParam(':itemId', $itemId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $products;
}

// Get merchant products with pagination using PDO
function getMerchantProducts($merchantId, $page = 1, $perPage = 10) {
    $pdo = getPDOConnection();
    
    // Calculate offset
    $offset = ($page - 1) * $perPage;
    
    // Get products with pagination
    $sql = "SELECT * FROM item WHERE merchantId = :merchantId ORDER BY dateAdded DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':merchantId', $merchantId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total products count for pagination
    $countSql = "SELECT COUNT(*) FROM item WHERE merchantId = :merchantId";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->bindParam(':merchantId', $merchantId, PDO::PARAM_INT);
    $countStmt->execute();
    $totalProducts = $countStmt->fetchColumn();
    
    return [
        'products' => $products,
        'total' => $totalProducts,
        'pages' => ceil($totalProducts / $perPage),
        'current_page' => $page
    ];
}

// Get product ratings and reviews using PDO
function getProductReviews($itemId) {
    $pdo = getPDOConnection();
    
    $sql = "
        SELECT r.*, u.username, u.firstname, u.lastname, u.profilePicture 
        FROM rating r
        JOIN user u ON r.userId = u.userId
        WHERE r.itemId = :itemId
        ORDER BY r.rateDate DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':itemId', $itemId, PDO::PARAM_INT);
    $stmt->execute();
    
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $reviews;
}

// Add product review using PDO
function addProductReview($userId, $itemId, $orderId, $rating, $comment) {
    $pdo = getPDOConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Add the review
        $sql = "INSERT INTO rating (userId, itemId, orderId, stars, comment, rateDate) VALUES (:userId, :itemId, :orderId, :stars, :comment, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':itemId', $itemId, PDO::PARAM_INT);
        $stmt->bindParam(':orderId', $orderId, PDO::PARAM_INT);
        $stmt->bindParam(':stars', $rating, PDO::PARAM_INT);
        $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);
        $stmt->execute();
        
        // Update order status
        $updateOrderSql = "UPDATE orders SET toRate = 0 WHERE orderId = :orderId";
        $updateOrderStmt = $pdo->prepare($updateOrderSql);
        $updateOrderStmt->bindParam(':orderId', $orderId, PDO::PARAM_INT);
        $updateOrderStmt->execute();
        
        // Update product rating average
        $updateItemSql = "
            UPDATE item 
            SET ratingAvg = (SELECT AVG(stars) FROM rating WHERE itemId = :itemId),
                ratingCount = (SELECT COUNT(*) FROM rating WHERE itemId = :itemId)
            WHERE itemId = :itemId
        ";
        $updateItemStmt = $pdo->prepare($updateItemSql);
        $updateItemStmt->bindParam(':itemId', $itemId, PDO::PARAM_INT);
        $updateItemStmt->execute();
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error adding review: " . $e->getMessage());
        return false;
    }
}

// Check if user has followed a merchant
function isFollowingMerchant($userId, $merchantId) {
    $pdo = getPDOConnection();
    
    $sql = "SELECT COUNT(*) FROM follows WHERE userId = :userId AND merchantId = :merchantId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':merchantId', $merchantId, PDO::PARAM_INT);
    $stmt->execute();
    
    return ($stmt->fetchColumn() > 0);
}

// Follow/unfollow merchant
function toggleFollowMerchant($userId, $merchantId) {
    $pdo = getPDOConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Check if already following
        $checkSql = "SELECT COUNT(*) FROM follows WHERE userId = :userId AND merchantId = :merchantId";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $checkStmt->bindParam(':merchantId', $merchantId, PDO::PARAM_INT);
        $checkStmt->execute();
        
        $isFollowing = ($checkStmt->fetchColumn() > 0);
        $merchantUserId = getUserIdFromMerchantId($merchantId);
        
        if ($isFollowing) {
            // Unfollow
            $sql = "DELETE FROM follows WHERE userId = :userId AND merchantId = :merchantId";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':merchantId', $merchantId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Update follower count
            $updateSql = "UPDATE user SET followers = followers - 1 WHERE userId = :merchantUserId";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->bindParam(':merchantUserId', $merchantUserId, PDO::PARAM_INT);
            $updateStmt->execute();
            
            $pdo->commit();
            return 'unfollowed';
        } else {
            // Follow
            $sql = "INSERT INTO follows (userId, merchantId, followDate) VALUES (:userId, :merchantId, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':merchantId', $merchantId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Update follower count
            $updateSql = "UPDATE user SET followers = followers + 1 WHERE userId = :merchantUserId";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->bindParam(':merchantUserId', $merchantUserId, PDO::PARAM_INT);
            $updateStmt->execute();
            
            // Create notification
            if ($merchantUserId) {
                $currentUser = getCurrentUser();
                $message = $currentUser['username'] . " started following your store!";
                createNotification($merchantUserId, $userId, $message);
            }
            
            $pdo->commit();
            return 'followed';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error toggling follow: " . $e->getMessage());
        return false;
    }
}

// Get followed merchants
function getFollowedMerchants($userId) {
    $pdo = getPDOConnection();
    
    $sql = "
        SELECT m.*, u.username, u.firstname, u.lastname, u.profilePicture 
        FROM follows f
        JOIN merchant m ON f.merchantId = m.merchantId
        JOIN user u ON m.userId = u.userId
        WHERE f.userId = :userId
        ORDER BY f.followDate DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $merchants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $merchants;
}

// Get merchant followers
function getMerchantFollowers($merchantId) {
    $pdo = getPDOConnection();
    
    $sql = "
        SELECT u.* 
        FROM follows f
        JOIN user u ON f.userId = u.userId
        WHERE f.merchantId = :merchantId
        ORDER BY f.followDate DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':merchantId', $merchantId, PDO::PARAM_INT);
    $stmt->execute();
    
    $followers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $followers;
}

// Get popular categories
function getPopularCategories($limit = 5) {
    $pdo = getPDOConnection();
    
    $sql = "
        SELECT c.*, COUNT(i.itemId) as item_count
        FROM categories c
        LEFT JOIN item i ON c.categoryId = i.categoryId
        WHERE c.active = 1
        GROUP BY c.categoryId
        ORDER BY item_count DESC
        LIMIT :limit
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $categories;
}

// Get trending products (most ordered in last week)
function getTrendingProducts($limit = 8) {
    $pdo = getPDOConnection();
    
    $sql = "
        SELECT i.*, COUNT(o.orderId) as order_count
        FROM item i
        JOIN orders o ON i.itemId = o.itemId
        WHERE o.orderDate >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND i.quantity > 0
        GROUP BY i.itemId
        ORDER BY order_count DESC
        LIMIT :limit
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $products;
}

// Get order count by status for dashboard
function getOrderCountsByStatus($userId, $isMerchant = false) {
    $pdo = getPDOConnection();
    
    if ($isMerchant) {
        $merchantId = getMerchantIdFromUserId(getConnection(), $userId);
        
        if (!$merchantId) {
            return [
                'toPay' => 0,
                'toShip' => 0,
                'toReceive' => 0,
                'toRate' => 0,
                'completed' => 0
            ];
        }
        
        $sql = "
            SELECT 
                SUM(toPay = 1) as toPay,
                SUM(toPay = 0 AND toShip = 1) as toShip,
                SUM(toShip = 0 AND toReceive = 1) as toReceive,
                SUM(toReceive = 0 AND toRate = 1) as toRate,
                SUM(toRate = 0) as completed
            FROM orders
            WHERE merchantId = :id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $merchantId, PDO::PARAM_INT);
    } else {
        $sql = "
            SELECT 
                SUM(toPay = 1) as toPay,
                SUM(toPay = 0 AND toShip = 1) as toShip,
                SUM(toShip = 0 AND toReceive = 1) as toReceive,
                SUM(toReceive = 0 AND toRate = 1) as toRate,
                SUM(toRate = 0) as completed
            FROM orders
            WHERE userId = :id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure all keys exist with at least 0 as value
    return [
        'toPay' => (int)($counts['toPay'] ?? 0),
        'toShip' => (int)($counts['toShip'] ?? 0),
        'toReceive' => (int)($counts['toReceive'] ?? 0),
        'toRate' => (int)($counts['toRate'] ?? 0),
        'completed' => (int)($counts['completed'] ?? 0)
    ];
}

// Get wishlist items
function getUserWishlist($userId) {
    $pdo = getPDOConnection();
    
    $sql = "
        SELECT w.*, i.itemName, i.picture, i.itemPrice, i.quantity, i.ratingAvg, m.storeName
        FROM wishlist w
        JOIN item i ON w.itemId = i.itemId
        JOIN merchant m ON i.merchantId = m.merchantId
        WHERE w.userId = :userId
        ORDER BY w.dateAdded DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $wishlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $wishlist;
}

// Add/remove item from wishlist
function toggleWishlistItem($userId, $itemId) {
    $pdo = getPDOConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Check if item is already in wishlist
        $checkSql = "SELECT COUNT(*) FROM wishlist WHERE userId = :userId AND itemId = :itemId";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $checkStmt->bindParam(':itemId', $itemId, PDO::PARAM_INT);
        $checkStmt->execute();
        
        $inWishlist = ($checkStmt->fetchColumn() > 0);
        
        if ($inWishlist) {
            // Remove from wishlist
            $sql = "DELETE FROM wishlist WHERE userId = :userId AND itemId = :itemId";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':itemId', $itemId, PDO::PARAM_INT);
            $stmt->execute();
            
            $pdo->commit();
            return 'removed';
        } else {
            // Add to wishlist
            $sql = "INSERT INTO wishlist (userId, itemId, dateAdded) VALUES (:userId, :itemId, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':itemId', $itemId, PDO::PARAM_INT);
            $stmt->execute();
            
            $pdo->commit();
            return 'added';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error toggling wishlist: " . $e->getMessage());
        return false;
    }
}

// Check if item is in wishlist
function isItemInWishlist($userId, $itemId) {
    $pdo = getPDOConnection();
    
    $sql = "SELECT COUNT(*) FROM wishlist WHERE userId = :userId AND itemId = :itemId";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':itemId', $itemId, PDO::PARAM_INT);
    $stmt->execute();
    
    return ($stmt->fetchColumn() > 0);
}