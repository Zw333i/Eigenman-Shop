<?php
// order/process_rating.php
session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['order_id']) && isset($_POST['rating'])) {
        $order_id = $_POST['order_id'];
        $rating = (int)$_POST['rating'];
        $review = isset($_POST['review']) ? trim($_POST['review']) : '';
        $user_id = $_SESSION['userId'];
        
        if ($rating < 1 || $rating > 5) {
            $_SESSION['error_message'] = "Invalid rating value. Please select between 1 and 5 stars.";
            header("Location: rate.php?id=$order_id");
            exit();
        }
        
        $conn = getConnection();
        
        $stmt = $conn->prepare("SELECT o.*, i.merchantId FROM Orders o JOIN Item i ON o.itemId = i.itemId WHERE o.orderId = ? AND o.userId = ? AND o.toRate = 1");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $_SESSION['error_message'] = "Invalid order or rating already submitted.";
            header("Location: history.php");
            exit();
        }
        
        $order = $result->fetch_assoc();
        
        $stmt = $conn->prepare("UPDATE Orders SET rating = ?, review = ?, toRate = 0, ratingDate = NOW() WHERE orderId = ?");
        $stmt->bind_param("isi", $rating, $review, $order_id);
        
        if ($stmt->execute()) {
            $merchant_id = $order['merchantId'];
            $message = "Your product received a " . $rating . "-star rating!";
            
            $stmt = $conn->prepare("INSERT INTO Notifications (receiverId, senderId, orderId, message, timestamp) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiis", $merchant_id, $user_id, $order_id, $message);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Thank you for your feedback! Your rating has been submitted successfully.";
            header("Location: details.php?id=$order_id");
            exit();
        } else {
            $_SESSION['error_message'] = "Failed to submit rating. Please try again.";
            header("Location: rate.php?id=$order_id");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Missing required information. Please try again.";
        header("Location: history.php");
        exit();
    }
} else {
    header("Location: history.php");
    exit();
}
?>