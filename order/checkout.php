<?php
// order/checkout.php
session_start();

require_once "../config/database.php";
require_once "../includes/functions.php";

if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

$userId = $_SESSION['userId'];
$conn = getConnection();

$sqlUser = "SELECT * FROM User WHERE userId = ?";
$stmtUser = $conn->prepare($sqlUser);
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$user = $resultUser->fetch_assoc();

$selectedItems = [];
$quantities = [];
$stockIssues = false;
$errorMessage = "";
$cartItems = [];
$totalCartPrice = 0;
$itemsByMerchant = [];
$totalShippingFee = 0;
$shippingFeePerMerchant = 40.00; // ₱40 per merchant
$orderTotal = 0;

$isDirectBuy = isset($_POST['direct_buy']) && $_POST['direct_buy'] == 1;

// deretso buy now
if ($isDirectBuy) {
    $itemId = (int)$_POST['direct_buy_item'];
    $quantity = (int)$_POST['direct_buy_quantity'];
    
    $sql = "SELECT i.*, m.storeName, i.merchantId 
            FROM Item i 
            JOIN Merchant m ON i.merchantId = m.merchantId 
            WHERE i.itemId = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $row['quantity'] = $quantity;
        $row['totalPrice'] = $row['itemPrice'] * $quantity;
        
        $selectedItems = [$itemId];
        $quantities = [$itemId => $quantity];
        $cartItems = [$row];
        $totalCartPrice = $row['totalPrice'];
        
        $merchantId = $row['merchantId'];
        $itemsByMerchant[$merchantId] = [
            'merchantId' => $merchantId,
            'storeName' => $row['storeName'],
            'items' => [$row],
            'subtotal' => $row['totalPrice']
        ];
        
        if ($quantity > $row['quantity']) {
            $stockIssues = true;
            $errorMessage = "Not enough stock available for " . $row['itemName'];
        }
        
        $_SESSION['direct_buy_data'] = [
            'itemId' => $itemId,
            'quantity' => $quantity
        ];
    } else {
        $_SESSION['error'] = "Product not found";
        header("Location: ../product/index.php");
        exit();
    }
} 
elseif (isset($_SESSION['direct_buy_data'])) {
    $itemId = (int)$_SESSION['direct_buy_data']['itemId'];
    $quantity = (int)$_SESSION['direct_buy_data']['quantity'];
    
    $sql = "SELECT i.*, m.storeName, i.merchantId 
            FROM Item i 
            JOIN Merchant m ON i.merchantId = m.merchantId 
            WHERE i.itemId = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $row['quantity'] = $quantity;
        $row['totalPrice'] = $row['itemPrice'] * $quantity;
        $row['stockQuantity'] = $row['quantity'];
        
        $selectedItems = [$itemId];
        $quantities = [$itemId => $quantity];
        $cartItems = [$row];
        $totalCartPrice = $row['totalPrice'];
        
        $merchantId = $row['merchantId'];
        $itemsByMerchant[$merchantId] = [
            'merchantId' => $merchantId,
            'storeName' => $row['storeName'],
            'items' => [$row],
            'subtotal' => $row['totalPrice']
        ];
        
        if ($quantity > $row['quantity']) {
            $stockIssues = true;
            $errorMessage = "Not enough stock available for " . $row['itemName'];
        }
    }
}
// cart checkout
else if (isset($_POST['selectedItems']) && !empty($_POST['selectedItems'])) {
    $selectedItems = $_POST['selectedItems'];
    $quantities = $_POST['quantities'] ?? [];


    $placeholders = implode(',', array_fill(0, count($selectedItems), '?'));
    $types = str_repeat('i', count($selectedItems));


    $sql = "SELECT c.*, i.itemName, i.itemPrice, i.picture, i.quantity as stockQuantity,
            i.brand, m.storeName, i.merchantId
            FROM UserCart c
            JOIN Item i ON c.itemId = i.itemId
            JOIN Merchant m ON i.merchantId = m.merchantId
            WHERE c.userId = ? AND c.itemId IN ($placeholders)";
           
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i".$types, $userId, ...$selectedItems);
    $stmt->execute();
    $result = $stmt->get_result();


    while ($row = $result->fetch_assoc()) {
        $itemQuantity = $quantities[$row['itemId']] ?? $row['quantity'];
        $row['quantity'] = $itemQuantity;
        $row['totalPrice'] = $row['itemPrice'] * $itemQuantity;
       
        $cartItems[] = $row;
        $totalCartPrice += $row['totalPrice'];
       
        $merchantId = $row['merchantId'];
        if (!isset($itemsByMerchant[$merchantId])) {
            $itemsByMerchant[$merchantId] = [
                'merchantId' => $merchantId,
                'storeName' => $row['storeName'],
                'items' => [],
                'subtotal' => 0
            ];
        }
        $itemsByMerchant[$merchantId]['items'][] = $row;
        $itemsByMerchant[$merchantId]['subtotal'] += $row['totalPrice'];
       
        $availableStock = $row['stockQuantity'] ?? $row['quantity'] ?? 0;
        if ($itemQuantity > $availableStock) {
            $stockIssues = true;
            $errorMessage = "Not enough stock available for " . $row['itemName'];
        }
    }
}
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['error'] = "Please select items to checkout.";
    header("Location: ../cart/view.php");
    exit();
}

$totalShippingFee = count($itemsByMerchant) * $shippingFeePerMerchant;
$orderTotal = $totalCartPrice + $totalShippingFee;

// Process checkout
if (isset($_POST['place_order']) && !empty($cartItems) && !$stockIssues) {
    if (isset($_SESSION['direct_buy_data'])) {
        unset($_SESSION['direct_buy_data']);
    }

    // Get delivery details
    $address = $_POST['address'];
    $contact = $_POST['contact'];
    $paymentMethod = $_POST['payment_method'];

    // Start transaction
    $conn->begin_transaction();

    try {
        foreach ($itemsByMerchant as $merchantData) {
            $merchantId = $merchantData['merchantId'];
            $orderDate = date('Y-m-d H:i:s');
            $eta = date('Y-m-d H:i:s', strtotime('+3 days'));

            foreach ($merchantData['items'] as $item) {
                $itemId = $item['itemId'];
                $itemQuantity = $item['quantity'];

                // ✅ Lock the item row to prevent race conditions
                $sqlCheckStock = "SELECT quantity FROM Item WHERE itemId = ? FOR UPDATE";
                $stmtCheckStock = $conn->prepare($sqlCheckStock);
                $stmtCheckStock->bind_param("i", $itemId);
                $stmtCheckStock->execute();
                $resultStock = $stmtCheckStock->get_result();

                if ($resultStock->num_rows === 0) {
                    throw new Exception("Item not found.");
                }

                $stockRow = $resultStock->fetch_assoc();
                $currentStock = (int)$stockRow['quantity'];

                if ($itemQuantity > $currentStock) {
                    throw new Exception("Not enough stock available for " . $item['itemName'] . ". Available: $currentStock, Requested: $itemQuantity");
                }

                // Proceed with placing the order
                $totalPrice = $item['totalPrice'];

                $sqlOrder = "INSERT INTO Orders (userId, merchantId, itemId, quantity, totalPrice, address, contact, modeOfPayment, orderDate, eta, toPay, toShip, toReceive, toRate) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmtOrder = $conn->prepare($sqlOrder);

                $toPay = ($paymentMethod == 'cod') ? 0 : 1;
                $toShip = 1;
                $toReceive = 0;
                $toRate = 0;

                $stmtOrder->bind_param("iiiidsssssiiii", 
                    $userId, $merchantId, $itemId, $itemQuantity, 
                    $totalPrice, $address, $contact, $paymentMethod, 
                    $orderDate, $eta, $toPay, $toShip, $toReceive, $toRate
                );
                $stmtOrder->execute();
                $orderId = $conn->insert_id;

                // Notify merchant
                $notifMessage = $user['username'] . " placed an order for " . $item['itemName'];
                $sqlNotif = "INSERT INTO Notifications (receiverId, senderId, orderId, message, timestamp) 
                             VALUES (?, ?, ?, ?, ?)";
                $stmtNotif = $conn->prepare($sqlNotif);
                $timestamp = date('Y-m-d H:i:s');

                $sqlMerchantUser = "SELECT userId FROM Merchant WHERE merchantId = ?";
                $stmtMerchantUser = $conn->prepare($sqlMerchantUser);
                $stmtMerchantUser->bind_param("i", $merchantId);
                $stmtMerchantUser->execute();
                $merchantUserResult = $stmtMerchantUser->get_result();
                $merchantUser = $merchantUserResult->fetch_assoc();
                $merchantUserId = $merchantUser['userId'];

                $stmtNotif->bind_param("iiiss", $merchantUserId, $userId, $orderId, $notifMessage, $timestamp);
                $stmtNotif->execute();

                // ✅ Update stock after re-checking
                $newStock = $currentStock - $itemQuantity;
                $sqlUpdateStock = "UPDATE Item SET quantity = ? WHERE itemId = ?";
                $stmtUpdateStock = $conn->prepare($sqlUpdateStock);
                $stmtUpdateStock->bind_param("ii", $newStock, $itemId);
                $stmtUpdateStock->execute();
            }
        }

        // Remove purchased items from cart (if not direct buy)
        if (!$isDirectBuy && !empty($selectedItems)) {
            $placeholders = implode(',', array_fill(0, count($selectedItems), '?'));
            $types = str_repeat('i', count($selectedItems));

            $sqlClearCart = "DELETE FROM UserCart WHERE userId = ? AND itemId IN ($placeholders)";
            $stmtClearCart = $conn->prepare($sqlClearCart);
            $stmtClearCart->bind_param("i" . $types, $userId, ...$selectedItems);
            $stmtClearCart->execute();
        }

        $conn->commit();

        $_SESSION['success'] = "Your order has been placed successfully!";
        header("Location: confirmation.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $errorMessage = "An error occurred while processing your order: " . $e->getMessage();

        // Re-save direct buy data if this was a direct buy
        if ($isDirectBuy) {
            $_SESSION['direct_buy_data'] = [
                'itemId' => $_POST['direct_buy_item'],
                'quantity' => $_POST['direct_buy_quantity']
            ];
        }
    }
}
?>

<?php include_once "../includes/header.php"; ?>
<?php include_once "../includes/navbar.php"; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<style>
    :root {
        --primary: #2563eb;
        --secondary: #64748b;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --light: #f8fafc;
        --dark: #1e293b;
        --border-color: #e2e8f0;
        --hover-bg: #f1f5f9;
    }

    body {
        background-color: #f9fafb;
    }

    .checkout-container {
        max-width: 1140px;
        margin: 2rem auto;
    }

    .checkout-header {
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-color);
    }

    .checkout-header h1 {
        font-size: 1.75rem;
        font-weight: 600;
        color: var(--dark);
    }

    .card, .merchant-card, .summary-card, .payment-option, .payment-details, .delivery-form {
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border-color);
        margin-bottom: 1.5rem;
        overflow: hidden;
        transition: all 0.2s ease-in-out;
    }

    .payment-option:hover, .merchant-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .delivery-form, .payment-methods {
        padding: 1.5rem;
    }

    .merchant-header, .summary-header {
        background-color: var(--light);
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }

    .checkout-item {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }

    .checkout-item:last-child {
        border-bottom: none;
    }

    .checkout-item-img {
        width: 70px;
        height: 70px;
        object-fit: cover;
        border-radius: 8px;
    }

    .item-name {
        font-weight: 600;
        color: var(--dark);
    }

    .form-control, .form-select {
        border-radius: 8px;
        padding: 0.75rem 1rem;
        border: 1px solid var(--border-color);
        transition: all 0.2s ease;
    }

    .form-control:focus, .form-select:focus {
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        border-color: var(--primary);
    }

    .form-label {
        font-weight: 500;
        margin-bottom: 0.5rem;
        color: var(--dark);
    }

    .form-text {
        color: var(--secondary);
    }

    .form-check-input:checked {
        background-color: var(--primary);
        border-color: var(--primary);
    }

    /* Payment methods */
    .payment-option {
        padding: 1rem;
        margin-bottom: 1rem;
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .payment-option.active {
        border-color: var(--primary);
        background-color: rgba(37, 99, 235, 0.05);
    }

    .payment-option:hover {
        background-color: var(--hover-bg);
    }

    .payment-details {
        padding: 1rem;
        margin: 0.5rem 0 1.5rem 1.5rem;
        background-color: var(--light);
    }

    /* Order summary */
    .summary-card {
        position: sticky;
        top: 20px;
    }

    .order-summary-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px dashed var(--border-color);
    }

    .order-summary-total {
        display: flex;
        justify-content: space-between;
        padding-top: 0.75rem;
        margin-top: 0.5rem;
        border-top: 2px solid var(--border-color);
    }

    .btn-place-order {
        background-color: var(--primary);
        border-color: var(--primary);
        font-weight: 600;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .btn-place-order:hover {
        background-color: #1d4ed8; /* Darker blue on hover */
        border-color: #1d4ed8;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .btn-outline-secondary {
        color: var(--secondary);
        border-color: var(--border-color);
        background-color: white;
        font-weight: 500;
        border-radius: 8px;
    }

    .btn-outline-secondary:hover {
        background-color: var(--hover-bg);
        color: var(--dark);
        border-color: var(--secondary);
    }

    .alert {
        border-radius: 8px;
        border: none;
    }

    .alert-danger {
        background-color: #fee2e2;
        color: #b91c1c;
    }

    .alert-warning {
        background-color: #fff7ed;
        color: #c2410c;
    }

    .alert-info {
        background-color: #eff6ff;
        color: #1e40af;
    }

    .shipping-info {
        padding: 0.75rem;
        border-radius: 8px;
        font-size: 0.875rem;
        text-align: center;
    }

    .free-shipping {
        background-color: #ecfdf5;
        color: #047857;
    }

    .shipping-goal {
        background-color: #eff6ff;
        color: #1e40af;
    }

    /* Badge styling */
    .badge {
        font-weight: 500;
        padding: 0.35rem 0.65rem;
        border-radius: 6px;
    }

    /* Animation classes */
    .fade-in {
        animation: fadeIn 0.5s ease-in;
    }

    .slide-up {
        animation: slideUp 0.5s ease-out;
    }

    .pulse {
        animation: pulse 2s infinite;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(37, 99, 235, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
        }
    }
    
    /* Responsive improvements */
    @media (max-width: 767.98px) {
        .checkout-item {
            padding: 0.75rem 1rem;
        }
        
        .checkout-item-img {
            width: 60px;
            height: 60px;
        }
        
        .item-name {
            font-size: 0.9rem;
        }
        
        .summary-card {
            position: static;
            margin-top: 1rem;
        }
    }
    
    @media (max-width: 575.98px) {
        .checkout-container {
            margin: 1rem auto;
        }
        
        .checkout-header h1 {
            font-size: 1.5rem;
        }
        
        .checkout-item-img {
            width: 50px;
            height: 50px;
        }
    }
</style>

<script>
function selectPayment(method) {
    // Set radio button
    document.getElementById(method).checked = true;
    
    document.querySelectorAll('.payment-option').forEach(option => {
        option.classList.remove('active');
    });
    event.currentTarget.classList.add('active');
    
    const gcashDetails = document.getElementById('gcash-details');
    const bankDetails = document.getElementById('bank-details');
    
    if (method === 'gcash') {
        gcashDetails.style.display = 'block';
        gcashDetails.classList.add('animate__animated', 'animate__fadeIn', 'animate__faster');
        bankDetails.style.display = 'none';
    } else if (method === 'bank') {
        bankDetails.style.display = 'block';
        bankDetails.classList.add('animate__animated', 'animate__fadeIn', 'animate__faster');
        gcashDetails.style.display = 'none';
    } else {
        gcashDetails.style.display = 'none';
        bankDetails.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const header = document.querySelector('.checkout-header');
    header.classList.add('animate__animated', 'animate__fadeIn');
    
    const deliveryForm = document.querySelector('.delivery-form');
    deliveryForm.classList.add('animate__animated', 'animate__fadeInUp');
    
    const paymentMethods = document.querySelector('.payment-methods');
    setTimeout(() => {
        paymentMethods.classList.add('animate__animated', 'animate__fadeInUp');
    }, 200);
    
    const merchantCards = document.querySelectorAll('.merchant-card');
    merchantCards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('animate__animated', 'animate__fadeInUp');
        }, 300 + (index * 100));
    });
    
    const summaryCard = document.querySelector('.summary-card');
    setTimeout(() => {
        summaryCard.classList.add('animate__animated', 'animate__fadeInRight');
    }, 400);
    
    const placeOrderBtn = document.querySelector('.btn-place-order');
    if (placeOrderBtn && !placeOrderBtn.disabled) {
        setTimeout(() => {
            placeOrderBtn.classList.add('pulse');
        }, 1500);
    }
});
</script>

<div class="container checkout-container">
    <div class="row">
        <div class="col-12">
            <div class="checkout-header d-flex justify-content-between align-items-center">
                <h1 class="mb-0">Checkout</h1>
                <a href="../cart/view.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Back to Cart
                </a>
            </div>
            
            <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4 animate__animated animate__fadeIn">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $errorMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($stockIssues): ?>
            <div class="alert alert-warning alert-dismissible fade show mb-4 animate__animated animate__fadeIn">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Warning:</strong> Some items in your cart have insufficient stock. Please review your cart before proceeding.
                <a href="../cart/view.php" class="btn btn-sm btn-outline-primary mt-2">Back to Cart</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <form method="post" action="" id="checkout-form">
                <?php if ($isDirectBuy) : ?>
                    <input type="hidden" name="direct_buy" value="1">
                    <input type="hidden" name="direct_buy_item" value="<?php echo $_POST['direct_buy_item']; ?>">
                    <input type="hidden" name="direct_buy_quantity" value="<?php echo $_POST['direct_buy_quantity']; ?>">
                <?php else: ?>
                    <?php foreach ($selectedItems as $itemId): ?>
                        <input type="hidden" name="selectedItems[]" value="<?php echo $itemId; ?>">
                    <?php endforeach; ?>
                    
                    <?php foreach ($quantities as $itemId => $quantity): ?>
                        <input type="hidden" name="quantities[<?php echo $itemId; ?>]" value="<?php echo $quantity; ?>">
                    <?php endforeach; ?>
                <?php endif; ?>
            
                <div class="delivery-form">
                    <h5 class="mb-3"><i class="fas fa-map-marker-alt me-2 text-primary"></i>Delivery Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                  value="<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contact" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="contact" name="contact" 
                                  value="<?php echo htmlspecialchars($user['contactNum']); ?>" required>
                            <div class="form-text">We'll contact you for delivery updates</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Delivery Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                        <div class="form-text">Please provide complete address including city and postal code</div>
                    </div>
                </div>
                
                <div class="payment-methods">
                    <h5 class="mb-3"><i class="fas fa-credit-card me-2 text-primary"></i>Payment Method</h5>
                    
                    <div class="payment-option active" onclick="selectPayment('cod')">
                        <div class="form-check d-flex align-items-center">
                            <input class="form-check-input" type="radio" name="payment_method" id="cod" value="cod" checked>
                            <label class="form-check-label ms-2 flex-grow-1" for="cod">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Cash on Delivery</h6>
                                        <p class="text-muted mb-0 small">Pay when you receive your order</p>
                                    </div>
                                    <i class="fas fa-money-bill-wave text-success"></i>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="payment-option" onclick="selectPayment('gcash')">
                        <div class="form-check d-flex align-items-center">
                            <input class="form-check-input" type="radio" name="payment_method" id="gcash" value="gcash">
                            <label class="form-check-label ms-2 flex-grow-1" for="gcash">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">GCash</h6>
                                        <p class="text-muted mb-0 small">Pay with your GCash account</p>
                                    </div>
                                    <i class="fas fa-wallet text-primary"></i>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="payment-option" onclick="selectPayment('bank')">
                        <div class="form-check d-flex align-items-center">
                            <input class="form-check-input" type="radio" name="payment_method" id="bank" value="bank">
                            <label class="form-check-label ms-2 flex-grow-1" for="bank">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Bank Transfer</h6>
                                        <p class="text-muted mb-0 small">Pay using bank transfer</p>
                                    </div>
                                    <i class="fas fa-university text-dark"></i>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div id="gcash-details" class="payment-details" style="display: none;">
                        <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>GCash Payment Details</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Account Number:</strong></p>
                                <p class="mb-3">0912 345 6789</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Account Name:</strong></p>
                                <p class="mb-0">Ei****an S.</p>
                            </div>
                        </div>
                        <div class="alert alert-info mt-2 mb-0 p-2">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Please include your Order ID as reference when sending payment
                        </div>
                    </div>
                    
                    <div id="bank-details" class="payment-details" style="display: none;">
                        <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Bank Transfer Details</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Bank:</strong></p>
                                <p class="mb-3">BDO</p>
                                
                                <p class="mb-1"><strong>Account Number:</strong></p>
                                <p class="mb-0">1234-5678-9012</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Account Name:</strong></p>
                                <p class="mb-3">Eigenman Anderson</p>
                                
                                <p class="mb-1"><strong>Branch:</strong></p>
                                <p class="mb-0">Main Branch (Manila)</p>
                            </div>
                        </div>
                        <div class="alert alert-info mt-2 mb-0 p-2">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Please include your Order ID as reference when sending payment
                        </div>
                    </div>
                </div>
                
                <h5 class="mt-4 mb-3"><i class="fas fa-shopping-basket me-2 text-primary"></i>Order Items</h5>
                
                <?php foreach ($itemsByMerchant as $merchantData): ?>
                <div class="merchant-card">
                    <div class="merchant-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <a href="../product/index.php?merchant=<?php echo $merchantData['merchantId']; ?>" class="text-decoration-none text-dark">
                                <i class="fas fa-store me-2 text-primary"></i>
                                <?php echo htmlspecialchars($merchantData['storeName']); ?>
                            </a>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($merchantData['items'] as $item): ?>
                        <div class="checkout-item">
                            <div class="row align-items-center">
                                <div class="col-md-2 col-3">
                                    <a href="../product/details.php?id=<?php echo $item['itemId']; ?>">
                                        <?php if (!empty($item['picture'])): ?>
                                            <img src="../<?php echo htmlspecialchars($item['picture']); ?>" 
                                                class="img-fluid rounded checkout-item-img" 
                                                alt="<?php echo htmlspecialchars($item['itemName']); ?>">
                                        <?php else: ?>
                                            <img src="../assets/images/product-placeholder.jpg" 
                                                class="img-fluid rounded checkout-item-img" 
                                                alt="Product Image">
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="col-md-6 col-6">
                                    <h6 class="item-name mb-1"><?php echo htmlspecialchars($item['itemName']); ?></h6>
                                    <p class="text-muted mb-1 small">
                                        <?php echo htmlspecialchars($item['brand']); ?>
                                    </p>
                                    <p class="mb-0">
                                        <span class="badge bg-light text-dark">
                                            ₱<?php echo number_format($item['itemPrice'], 2); ?>
                                        </span>
                                        <span class="ms-2">x <?php echo $item['quantity']; ?></span>
                                    </p>
                                    <?php 
                                        $availableStock = $item['stockQuantity'] ?? $item['quantity'] ?? 0;
                                        if ($item['quantity'] > $availableStock): ?>
                                        <p class="text-danger mb-0 mt-1 small">
                                            <i class="fas fa-exclamation-circle me-1"></i>
                                            Only <?php echo $availableStock; ?> in stock!
                                        </p>
                                        <?php endif; ?>
                                </div>
                                <div class="col-md-4 col-3 text-end">
                                    <h6 class="fw-bold mb-0">₱<?php echo number_format($item['totalPrice'], 2); ?></h6>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="d-flex justify-content-between p-3 bg-light">
                            <div>
                                <span class="d-block small">Subtotal</span>
                                <span class="d-block mt-2 small">Shipping</span>
                            </div>
                            <div class="text-end">
                                <span class="d-block small">₱<?php echo number_format($merchantData['subtotal'], 2); ?></span>
                                <span class="d-block mt-2 small">₱<?php echo number_format($shippingFeePerMerchant, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </form>
        </div>
        
        <div class="col-lg-4">
            <div class="summary-card">
                <div class="summary-header">
                    <h5 class="mb-0"><i class="fas fa-receipt me-2 text-primary"></i>Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="order-summary-item">
                        <span class="small">Items (<?php echo count($cartItems); ?>)</span>
                        <span class="fw-bold small">₱<?php echo number_format($totalCartPrice, 2); ?></span>
                    </div>
                    
                    <div class="order-summary-item">
                        <span class="small">Shipping (<?php echo count($itemsByMerchant); ?> merchant<?php echo count($itemsByMerchant) > 1 ? 's' : ''; ?>)</span>
                        <span class="fw-bold small">₱<?php echo number_format($totalShippingFee, 2); ?></span>
                    </div>
                    
                    <?php if ($totalCartPrice >= 5000): ?>
                    <div class="order-summary-item text-success">
                        <span class="small"><i class="fas fa-gift me-2"></i>Free Shipping</span>
                        <span class="small">-₱<?php echo number_format($totalShippingFee, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="order-summary-total">
                        <h6 class="mb-0">Total Payment</h6>
                        <h5 class="mb-0 text-primary">
                            ₱<?php echo number_format($totalCartPrice >= 5000 ? $totalCartPrice : $orderTotal, 2); ?>
                        </h5>
                    </div>
                    
                    <div class="mt-3">
                        <?php if (!$stockIssues): ?>
                        <button type="submit" form="checkout-form" name="place_order" class="btn btn-primary w-100 btn-place-order">
                            <i class="fas fa-check-circle me-2"></i>Place Order
                        </button>
                        <?php else: ?>
                        <button class="btn btn-secondary w-100" disabled>
                            <i class="fas fa-exclamation-circle me-2"></i>Stock Issues
                        </button>
                        <?php endif; ?>
                        
                        <a href="../cart/view.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">
                            <i class="fas fa-arrow-left me-2"></i>Back to Cart
                        </a>
                    </div>
                    
                    <?php if ($totalCartPrice >= 5000): ?>
                    <div class="shipping-info free-shipping mt-3">
                        <i class="fas fa-truck me-2"></i>
                        <span class="small">Your order qualifies for FREE shipping!</span>
                    </div>
                    <?php elseif ($totalCartPrice > 0): ?>
                    <div class="shipping-info shipping-goal mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <span class="small">Add ₱<?php echo number_format(5000 - $totalCartPrice, 2); ?> more for FREE shipping!</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once "../includes/footer.php"; ?>
