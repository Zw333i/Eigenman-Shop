<?php
// cart/update.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'customer') {
    // Return JSON response for AJAX requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit();
    }
    
    // Redirect for regular requests
    header("Location: ../auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$userId = $_SESSION['userId'];
$response = ['success' => false];

// Handle DELETE requests for item removal
if ((isset($_GET['itemId']) && isset($_GET['delete']) && $_GET['delete'] == 1) || 
    (isset($_POST['itemId']) && isset($_POST['delete']) && $_POST['delete'] == 1)) {
    
    $itemId = isset($_GET['itemId']) ? $_GET['itemId'] : $_POST['itemId'];
    
    // Make sure itemId is valid
    if (!is_numeric($itemId)) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
            exit();
        }
        
        $_SESSION['error'] = "Invalid item ID.";
        header("Location: view.php");
        exit();
    }
    
    $deleteQuery = "DELETE FROM UserCart WHERE userId = ? AND itemId = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("ii", $userId, $itemId);
    
    if ($stmt->execute()) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => true]);
            exit();
        }
        
        $_SESSION['success'] = "Item removed from cart successfully!";
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => 'Failed to remove item from cart']);
            exit();
        }
        
        $_SESSION['error'] = "Failed to remove item from cart. Please try again.";
    }
    
    $stmt->close();
    header("Location: view.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['itemId']) && isset($_POST['quantity'])) {
    $itemId = $_POST['itemId'];
    $quantity = $_POST['quantity'];
    
    if (!is_numeric($itemId) || !is_numeric($quantity) || $quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }
    
    $availabilityQuery = "SELECT quantity FROM Item WHERE itemId = ?";
    $stmt = $conn->prepare($availabilityQuery);
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $availabilityResult = $stmt->get_result();
    
    if ($availabilityResult->num_rows > 0) {
        $availabilityRow = $availabilityResult->fetch_assoc();
        $availableQuantity = $availabilityRow['quantity'];
        
        if ($quantity > $availableQuantity) {
            echo json_encode([
                'success' => false, 
                'message' => 'Requested quantity exceeds available stock'
            ]);
            exit();
        }
    }
    $stmt->close();
    
    $updateQuery = "UPDATE UserCart SET quantity = ? WHERE userId = ? AND itemId = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("iii", $quantity, $userId, $itemId);
    
    if ($stmt->execute()) {
        $priceQuery = "SELECT itemPrice FROM Item WHERE itemId = ?";
        $stmtPrice = $conn->prepare($priceQuery);
        $stmtPrice->bind_param("i", $itemId);
        $stmtPrice->execute();
        $priceResult = $stmtPrice->get_result();
        $unitPrice = 0;
        
        if ($priceResult->num_rows > 0) {
            $priceRow = $priceResult->fetch_assoc();
            $unitPrice = $priceRow['itemPrice'];
        }
        $stmtPrice->close();
        
        echo json_encode([
            'success' => true, 
            'unitPrice' => $unitPrice,
            'totalPrice' => $unitPrice * $quantity
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
    }
    
    $stmt->close();
    exit();
}

echo json_encode(['success' => false, 'message' => 'No valid action specified']);
?>