<?php
// merchant/mark_shipped
require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/config/database.php';

if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'merchant') {
    header("Location: /e-commerce/auth/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: /e-commerce/merchant/orders.php");
    exit();
}

$orderId = $_GET['id'];
$merchantId = $_SESSION['userId'];

// Verify the order belongs to this merchant
$query = "SELECT orderId FROM Orders WHERE orderId = ? AND merchantId = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $orderId, $merchantId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: /e-commerce/merchant/orders.php");
    exit();
}

$query = "UPDATE Orders SET toShip = 0, toReceive = 1 WHERE orderId = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $orderId);

if ($stmt->execute()) {
    $message = "Your order #$orderId has been shipped!";
    $query = "INSERT INTO Notifications (receiverId, senderId, orderId, message, timestamp) 
              SELECT userId, ?, ?, ?, NOW() FROM Orders WHERE orderId = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisi", $merchantId, $orderId, $message, $orderId);
    $stmt->execute();
    
    $_SESSION['success'] = "Order marked as shipped successfully!";
} else {
    $_SESSION['error'] = "Error updating order status.";
}

header("Location: /e-commerce/merchant/orders.php");
exit();
?>