<?php
// cart/add.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'customer') {
    $_SESSION['error_message'] = 'You must be logged in as a customer to add items to cart';
    header('Location: ../auth/login.php');
    exit;
}

$userId = $_SESSION['userId'];
$itemId = 0;
$quantity = 1;
$price = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = isset($_POST['itemId']) ? (int)$_POST['itemId'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $itemId = isset($_GET['itemId']) ? (int)$_GET['itemId'] : 0;
    $quantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1;
    
    if ($itemId > 0) {
        $stmt = $conn->prepare("SELECT itemPrice FROM Item WHERE itemId = ?");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $item = $result->fetch_assoc();
            $price = $item['itemPrice'];
        }
        $stmt->close();
    }
}

if ($itemId <= 0) {
    $_SESSION['error_message'] = 'Invalid product ID';
    header('Location: ../product/index.php');
    exit;
}

if ($quantity <= 0) {
    $_SESSION['error_message'] = 'Quantity must be greater than zero';
    header('Location: ../product/details.php?id=' . $itemId);
    exit;
}

$stmt = $conn->prepare("SELECT quantity, merchantId FROM Item WHERE itemId = ?");
$stmt->bind_param("i", $itemId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'Product not found';
    header('Location: ../product/index.php');
    exit;
}

$item = $result->fetch_assoc();
$merchantId = $item['merchantId'];

if ($quantity > $item['quantity']) {
    $_SESSION['error_message'] = 'Not enough stock available. Only ' . $item['quantity'] . ' items left.';
    header('Location: ../product/details.php?id=' . $itemId);
    exit;
}
$stmt->close();

$totalPrice = $price * $quantity;

$stmt = $conn->prepare("SELECT quantity, totalPrice FROM UserCart WHERE userId = ? AND itemId = ?");
$stmt->bind_param("ii", $userId, $itemId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $cartItem = $result->fetch_assoc();
    $newQuantity = $cartItem['quantity'] + $quantity;
    $newTotalPrice = $price * $newQuantity;
    
    if ($newQuantity > $item['quantity']) {
        $_SESSION['error_message'] = 'Cannot add more items. Cart would exceed available stock.';
        header('Location: ../product/details.php?id=' . $itemId);
        exit;
    }
    
    $updateStmt = $conn->prepare("UPDATE UserCart SET quantity = ?, totalPrice = ? WHERE userId = ? AND itemId = ?");
    $updateStmt->bind_param("idii", $newQuantity, $newTotalPrice, $userId, $itemId);
    $updateStmt->execute();
    $updateStmt->close();
    
    $_SESSION['success_message'] = 'Item quantity updated in your cart';
} else {
    $insertStmt = $conn->prepare("INSERT INTO UserCart (userId, itemId, quantity, totalPrice) VALUES (?, ?, ?, ?)");
    $insertStmt->bind_param("iiid", $userId, $itemId, $quantity, $totalPrice);
    $insertStmt->execute();
    $insertStmt->close();
    
    $_SESSION['success_message'] = 'Item added to your cart';
}
$stmt->close();

if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'details.php') !== false) {
    header('Location: ../product/details.php?id=' . $itemId);
} else {
    header('Location: ../product/index.php');
}
exit;
?>