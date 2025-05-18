<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/config/database.php';

if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'merchant') {
    header("Location: /e-commerce/auth/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: /e-commerce/merchant/products.php");
    exit();
}

$productId = $_GET['id'];
$merchantId = $_SESSION['userId'];

$query = "SELECT picture FROM Item WHERE itemId = ? AND merchantId = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $productId, $merchantId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: /e-commerce/merchant/products.php");
    exit();
}

$product = $result->fetch_assoc();

$query = "DELETE FROM Item WHERE itemId = ? AND merchantId = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $productId, $merchantId);

if ($stmt->execute()) {
    if (!empty($product['picture']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/e-commerce/' . $product['picture'])) {
        unlink($_SERVER['DOCUMENT_ROOT'] . '/e-commerce/' . $product['picture']);
    }
    
    $_SESSION['success'] = "Product deleted successfully!";
} else {
    $_SESSION['error'] = "Error deleting product.";
}

header("Location: /e-commerce/merchant/products.php");
exit();
?>