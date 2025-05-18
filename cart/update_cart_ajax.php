<?php
// cart/update_cart_ajax.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'customer') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You must be logged in to update your cart.']);
    exit();
}

$userId = $_SESSION['userId'];
$response = ['success' => false];

if (isset($_POST['itemId']) && isset($_POST['quantity'])) {
    $itemId = intval($_POST['itemId']);
    $quantity = intval($_POST['quantity']);
    
    if ($quantity <= 0) {
        $response['message'] = 'Quantity must be greater than 0.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    $checkItem = "SELECT i.quantity as availableQuantity, i.itemPrice, i.merchantId 
                  FROM Item i 
                  JOIN usercart uc ON i.itemId = uc.itemId 
                  WHERE i.itemId = ? AND uc.userId = ?";
    
    $stmt = $conn->prepare($checkItem);
    $stmt->bind_param("ii", $itemId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $response['message'] = 'Item not found in your cart.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    $itemData = $result->fetch_assoc();
    $merchantId = $itemData['merchantId'];
    
    if ($quantity > $itemData['availableQuantity']) {
        $response['message'] = 'Not enough stock available. Maximum available: ' . $itemData['availableQuantity'];
        $response['currentQuantity'] = min($itemData['availableQuantity'], $quantity);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    $updateCart = "UPDATE usercart SET quantity = ? WHERE userId = ? AND itemId = ?";
    $stmt = $conn->prepare($updateCart);
    $stmt->bind_param("iii", $quantity, $userId, $itemId);
    
    if ($stmt->execute()) {
        $unitPrice = $itemData['itemPrice'];
        $totalPrice = $unitPrice * $quantity;
        
        $formattedTotalPrice = number_format($totalPrice, 2);
        
        $getMerchantSubtotal = "SELECT SUM(uc.quantity * i.itemPrice) as subtotal 
                                FROM usercart uc 
                                JOIN Item i ON uc.itemId = i.itemId 
                                WHERE uc.userId = ? AND i.merchantId = ?";
        $stmt = $conn->prepare($getMerchantSubtotal);
        $stmt->bind_param("ii", $userId, $merchantId);
        $stmt->execute();
        $subtotalResult = $stmt->get_result();
        $subtotalData = $subtotalResult->fetch_assoc();
        $merchantSubtotal = $subtotalData['subtotal'];
        
        $response = [
            'success' => true,
            'itemTotalPrice' => $formattedTotalPrice,
            'itemTotalPriceRaw' => $totalPrice,
            'merchantId' => $merchantId,
            'merchantSubtotal' => number_format($merchantSubtotal, 2)
        ];
    } else {
        $response['message'] = 'Failed to update cart. Please try again.';
    }
    
    $stmt->close();
}

if (isset($_POST['itemId']) && isset($_POST['delete'])) {
    $itemId = intval($_POST['itemId']);
    
    $getMerchantQuery = "SELECT m.merchantId FROM Item i 
                         JOIN Merchant m ON i.merchantId = m.merchantId 
                         WHERE i.itemId = ?";
    $stmt = $conn->prepare($getMerchantQuery);
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $merchantResult = $stmt->get_result();
    
    if ($merchantResult->num_rows === 0) {
        $response['message'] = 'Item not found.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    $merchantData = $merchantResult->fetch_assoc();
    $merchantId = $merchantData['merchantId'];
    
    $deleteItem = "DELETE FROM usercart WHERE userId = ? AND itemId = ?";
    $stmt = $conn->prepare($deleteItem);
    $stmt->bind_param("ii", $userId, $itemId);
    
    if ($stmt->execute()) {
        $getMerchantSubtotal = "SELECT SUM(uc.quantity * i.itemPrice) as subtotal 
                                FROM usercart uc 
                                JOIN Item i ON uc.itemId = i.itemId 
                                WHERE uc.userId = ? AND i.merchantId = ?";
        $stmt = $conn->prepare($getMerchantSubtotal);
        $stmt->bind_param("ii", $userId, $merchantId);
        $stmt->execute();
        $subtotalResult = $stmt->get_result();
        $subtotalData = $subtotalResult->fetch_assoc();
        $merchantSubtotal = $subtotalData['subtotal'] ?? 0;
        
        $response = [
            'success' => true,
            'merchantId' => $merchantId,
            'merchantSubtotal' => number_format($merchantSubtotal, 2)
        ];
    } else {
        $response['message'] = 'Failed to remove item from cart. Please try again.';
    }
    
    $stmt->close();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>