<?php
session_start();
require_once '../includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$conn = getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_POST['action']) && $_POST['action'] == 'cancel_order' && isset($_POST['order_id'])) {
        $order_id = $_POST['order_id'];
        
        $stmt = $conn->prepare("
            SELECT * FROM orders 
            WHERE id = ? AND user_id = ? AND status IN ('pending', 'processing')
        ");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Order has been cancelled successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to cancel order. Please try again.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid order or order cannot be cancelled at this time.";
        }
        
        header("Location: details.php?id=" . $order_id);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] == 'reorder' && isset($_POST['order_id'])) {
        $order_id = $_POST['order_id'];
        
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT oi.product_id, oi.quantity 
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ? AND p.active = 1
            ");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $success = false;
            
            while ($item = $result->fetch_assoc()) {
                $stmt = $conn->prepare("
                    SELECT * FROM cart 
                    WHERE user_id = ? AND product_id = ?
                ");
                $stmt->bind_param("ii", $user_id, $item['product_id']);
                $stmt->execute();
                $cart_result = $stmt->get_result();
                
                if ($cart_result->num_rows > 0) {
                    $cart_item = $cart_result->fetch_assoc();
                    $new_quantity = $cart_item['quantity'] + $item['quantity'];
                    
                    $stmt = $conn->prepare("
                        UPDATE cart 
                        SET quantity = ? 
                        WHERE id = ?
                    ");
                    $stmt->bind_param("ii", $new_quantity, $cart_item['id']);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO cart (user_id, product_id, quantity) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->bind_param("iii", $user_id, $item['product_id'], $item['quantity']);
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
        
        header("Location: history.php");
        exit;
    }
}

header("Location: history.php");
exit;
?>