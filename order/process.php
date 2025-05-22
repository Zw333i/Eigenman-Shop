<?php
// process.php - Updated cancel order logic
session_start();
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $conn = getConnection();
    
    if ($_POST['action'] == 'cancel_order') {
        $order_id = $_POST['order_id'];
        $user_id = $_SESSION['userId'];
        
        // Validate inputs
        if (!is_numeric($order_id) || !is_numeric($user_id)) {
            $_SESSION['error_message'] = "Invalid order or user ID.";
            header("Location: history.php");
            exit;
        }
        
        // Start transaction to ensure data consistency
        $conn->begin_transaction();
        
        try {
            // First, get the order details to retrieve quantity and item info
            // Also check if order can be cancelled (only toPay or toShip orders)
            $stmt = $conn->prepare("SELECT itemId, quantity, toPay, toShip FROM Orders WHERE orderId = ? AND userId = ?");
            $stmt->bind_param("ii", $order_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Order not found or unauthorized access");
            }
            
            $order = $result->fetch_assoc();
            
            // Check if order can be cancelled (only if toPay or toShip is true)
            if (!$order['toPay'] && !$order['toShip']) {
                throw new Exception("Order cannot be cancelled at this stage");
            }
            
            $item_id = $order['itemId'];
            $cancelled_quantity = $order['quantity'];
            
            // Return the quantity back to the item stock
            $stmt = $conn->prepare("UPDATE Item SET quantity = quantity + ? WHERE itemId = ?");
            $stmt->bind_param("ii", $cancelled_quantity, $item_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to restore item quantity");
            }
            
            // Verify that the update actually happened
            if ($stmt->affected_rows === 0) {
                throw new Exception("No item found to restore quantity");
            }
            
            // Delete the order
            $stmt = $conn->prepare("DELETE FROM Orders WHERE orderId = ? AND userId = ?");
            $stmt->bind_param("ii", $order_id, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to cancel order");
            }
            
            // Verify that the order was actually deleted
            if ($stmt->affected_rows === 0) {
                throw new Exception("Order not found for deletion");
            }
            
            // Commit the transaction
            $conn->commit();
            
            // Set success message
            $_SESSION['success_message'] = "Order #$order_id cancelled successfully. Item quantity has been restored.";
            
        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollback();
            $_SESSION['error_message'] = "Error cancelling order: " . $e->getMessage();
        }
        
        // Redirect back to order history
        header("Location: history.php");
        exit;
    }
    
    // Handle other actions here if needed
    // For example: process_payment, update_order, etc.
    else {
        $_SESSION['error_message'] = "Invalid action specified.";
        header("Location: history.php");
        exit;
    }
}

// If not a POST request, redirect to history
else {
    header("Location: history.php");
    exit;
}

// wag na to hirap paganahin
/*
function cancelOrderWithStatus($conn, $order_id, $user_id) {
    $conn->begin_transaction();
    
    try {
        // Get order details - only if not already cancelled
        $stmt = $conn->prepare("SELECT itemId, quantity, toPay, toShip FROM Orders WHERE orderId = ? AND userId = ? AND cancelled IS NULL");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Order not found or already cancelled");
        }
        
        $order = $result->fetch_assoc();
        
        // Check if order can be cancelled
        if (!$order['toPay'] && !$order['toShip']) {
            throw new Exception("Order cannot be cancelled at this stage");
        }
        
        // Restore quantity to item
        $stmt = $conn->prepare("UPDATE Item SET quantity = quantity + ? WHERE itemId = ?");
        $stmt->bind_param("ii", $order['quantity'], $order['itemId']);
        $stmt->execute();
        
        // Mark order as cancelled
        $stmt = $conn->prepare("UPDATE Orders SET cancelled = NOW() WHERE orderId = ? AND userId = ?");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}
*/
?>