<?php
// api/get_order.php
session_start();

if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'merchant') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/config/database.php';

$userId = $_SESSION['userId'];
$response = ['success' => false];

if (isset($_GET['orderId'])) {
    $orderId = intval($_GET['orderId']);
    
    // Verify that this merchant owns this order
    $sql = "SELECT o.*, i.itemName, i.picture, u.firstname, u.lastname, u.contactNum 
            FROM Orders o
            INNER JOIN Item i ON o.itemId = i.itemId
            INNER JOIN User u ON o.userId = u.userId
            WHERE o.orderId = $orderId AND o.merchantId = $userId";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $order = $result->fetch_assoc();
        $response = [
            'success' => true,
            'order' => $order
        ];
    } else {
        $response['message'] = "Order not found or you don't have permission to access it.";
    }
} else {
    $response['message'] = "No order ID provided.";
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>