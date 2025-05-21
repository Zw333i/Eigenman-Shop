<?php

session_start();
require_once '../includes/functions.php';
requireLogin();

if (!isset($_SESSION['userId'])) {
    die("User is not logged in. Session 'userId' is not set.");
}

$user_id = $_SESSION['userId'];
$conn = getConnection();

// Error Reporting for Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the Order ID
$order_id = $_POST['order_id'] ?? $_GET['order_id'] ?? null;

if (!$order_id) {
    die("Order ID is missing.");
}

echo "Order ID: $order_id<br>";
echo "User ID: $user_id<br>";

// Check if the request is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ============================
    // Handle Order Cancellation
    // ============================
    if (isset($_POST['action']) && $_POST['action'] == 'cancel_order') {
        
        // Verify if the order exists and belongs to the user
        $stmt = $conn->prepare("
            SELECT * FROM orders 
            WHERE orderId = ? AND userId = ? AND toPay IN (0, 1)
        ");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "Order Found!<br>";

            // Update the order status to canceled
            $updateStmt = $conn->prepare("UPDATE orders SET toPay = 0, toShip = 0, toReceive = 0 WHERE orderId = ? AND userId = ?");
            $updateStmt->bind_param("ii", $order_id, $user_id);

            if ($updateStmt->execute()) {
                $_SESSION['success_message'] = "Order has been cancelled successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to cancel the order. Please try again.";
            }
        } else {
            echo "Order Not Found!<br>";
            $_SESSION['error_message'] = "Invalid order or order cannot be cancelled at this time.";
        }
    }
    
    // ============================
    // Handle Order Reorder Logic
    // ============================
    if (isset($_POST['action']) && $_POST['action'] == 'reorder') {
        $stmt = $conn->prepare("
            SELECT * FROM orders 
            WHERE orderId = ? AND userId = ?
        ");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT oi.itemId, oi.quantity 
                FROM order_items oi
                JOIN products p ON oi.itemId = p.itemId
                WHERE oi.orderId = ? AND p.active = 1
            ");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $success = false;

            // Add items to the cart
            while ($item = $result->fetch_assoc()) {
                $stmt = $conn->prepare("
                    SELECT * FROM cart 
                    WHERE userId = ? AND itemId = ?
                ");
                $stmt->bind_param("ii", $user_id, $item['itemId']);
                $stmt->execute();
                $cart_result = $stmt->get_result();
                
                if ($cart_result->num_rows > 0) {
                    $cart_item = $cart_result->fetch_assoc();
                    $new_quantity = $cart_item['quantity'] + $item['quantity'];

                    $stmt = $conn->prepare("
                        UPDATE cart 
                        SET quantity = ? 
                        WHERE cartId = ?
                    ");
                    $stmt->bind_param("ii", $new_quantity, $cart_item['cartId']);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO cart (userId, itemId, quantity) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->bind_param("iii", $user_id, $item['itemId'], $item['quantity']);
                    $stmt->execute();
                }
                
                $success = true;
            }

            if ($success) {
                $_SESSION['success_message'] = "Items have been added to your cart.";
                header("Location: ../cart/view.php");
                exit;
            } else {
                $_SESSION['error_message'] = "No items could be added to your cart. Products may no longer be available.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid order.";
        }
    }
}

// Redirect back to the order history page
header("Location: history.php");
exit;
?>