<?php
session_start();
require_once '../includes/functions.php';

// \order\history.php
requireLogin();

$title = "Order History";
$user = getCurrentUser();

$conn = getConnection();

if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['userId'];

$stmt = $conn->prepare("
    SELECT o.orderId, o.orderDate, o.totalPrice, o.toPay, o.toShip, 
           o.toReceive, o.toRate, o.quantity, o.modeOfPayment, o.address,
           i.itemName, i.itemPrice, i.picture, m.storeName,
           o.rating, o.review
    FROM Orders o
    INNER JOIN Item i ON o.itemId = i.itemId
    INNER JOIN Merchant m ON o.merchantId = m.merchantId
    WHERE o.userId = ?
    ORDER BY o.orderDate DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$results = $stmt->get_result();

$orders = [];
while ($row = $results->fetch_assoc()) {
    $row['totalPrice'] = '₱' . number_format($row['totalPrice'], 2);
    $row['itemPrice'] = '₱' . number_format($row['itemPrice'], 2);
    
    if (!empty($row['picture'])) {
        $row['picture'] = '../' . ltrim($row['picture'], './');
    }
    
    $orders[] = $row;
}

if (!empty($user['profilePicture'])) {
    $user['profilePicture'] = '../' . ltrim($user['profilePicture'], './');
}

include_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-3 mb-4">
            <!-- Account Sidebar -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="user-profile text-center p-4 border-bottom">
                        <?php if (!empty($user['profilePicture'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profilePicture']); ?>" 
                            class="rounded-circle mb-3" 
                            style="width: 120px; height: 120px; object-fit: cover;" 
                            alt="Profile Picture"
                            onerror="this.onerror=null;this.src='../assets/images/default-profile.jpg';">
                        <?php else: ?>
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                                <i class="bi bi-person" style="font-size: 2rem"></i>
                            </div>
                        <?php endif; ?>
                        <h5 class="mb-1"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h5>
                        <p class="text-muted small mb-0"><?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="../auth/profile.php" class="list-group-item list-group-item-action border-0 px-4 py-3">
                            <i class="bi bi-person-circle me-2"></i> My Profile
                        </a>
                        <a href="history.php" class="list-group-item list-group-item-action border-0 px-4 py-3 active" aria-current="true">
                            <i class="bi bi-box-seam me-2"></i> Order History
                        </a>
                        <a href="../cart/view.php" class="list-group-item list-group-item-action border-0 px-4 py-3">
                            <i class="bi bi-cart me-2"></i> My Cart
                        </a>
                        <a href="../auth/logout.php" class="list-group-item list-group-item-action border-0 px-4 py-3 text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Order History</h5>
                    <a href="../product/index.php" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-shop me-1"></i> Continue Shopping
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="bi bi-box2 text-muted" style="font-size: 4rem;"></i>
                            </div>
                            <h5 class="mb-3">No Orders Yet</h5>
                            <p class="text-muted mb-4">You haven't placed any orders yet.</p>
                            <a href="../product/index.php" class="btn btn-primary">
                                <i class="bi bi-shop me-1"></i> Browse Products
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Order Details</th>
                                        <th scope="col">Price</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="border-0">
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
<td>
    <div class="d-flex align-items-center">
        <div class="flex-shrink-0">
            <a href="details.php?id=<?php echo $order['orderId']; ?>">
                <?php if (!empty($order['picture'])): ?>
                    <img src="<?php echo htmlspecialchars($order['picture']); ?>" 
                         class="img-thumbnail" 
                         alt="<?php echo htmlspecialchars($order['itemName']); ?>" 
                         width="80"
                         onerror="this.onerror=null;this.src='../assets/images/placeholder-product.jpg';">
                <?php else: ?>
                    <div class="bg-light d-flex align-items-center justify-content-center rounded" style="width: 80px; height: 80px;">
                        <i class="bi bi-image text-muted"></i>
                    </div>
                <?php endif; ?>
            </a>
        </div>
        <div class="ms-3">
            <h6 class="mb-1">
                <a href="details.php?id=<?php echo $order['orderId']; ?>" class="text-decoration-none text-dark">
                    <?php echo htmlspecialchars($order['itemName']); ?>
                </a>
            </h6>
            <p class="text-muted small mb-0">
                <span class="me-2">Qty: <?php echo $order['quantity']; ?></span>
                <span class="me-2">|</span>
                <span>Store: <?php echo htmlspecialchars($order['storeName']); ?></span>
            </p>
            <p class="text-muted small mb-0">
                <span>Order Date: <?php echo date('d M Y', strtotime($order['orderDate'])); ?></span>
            </p>
        </div>
    </div>
</td>
                                            <td class="fw-medium">
                                                <?php echo $order['totalPrice']; ?>
                                                <p class="text-muted small mb-0"><?php echo htmlspecialchars($order['modeOfPayment']); ?></p>
                                            </td>
                                            <td>
                                                <?php
                                                    $status = 'Processing';
                                                    $status_class = 'bg-info';
                                                    
                                                    if ($order['toPay']) {
                                                        $status = 'To Pay';
                                                        $status_class = 'bg-warning text-dark';
                                                    } elseif ($order['toShip']) {
                                                        $status = 'To Ship';
                                                        $status_class = 'bg-info text-dark';
                                                    } elseif ($order['toReceive']) {
                                                        $status = 'To Receive';
                                                        $status_class = 'bg-primary';
                                                    } elseif ($order['toRate']) {
                                                        $status = 'To Rate';
                                                        $status_class = 'bg-success';
                                                    } elseif ($order['rating'] !== null || $order['review'] !== null) {
                                                        // If there's a rating or review, it's completed
                                                        $status = 'Completed';
                                                        $status_class = 'bg-success';
                                                    } elseif ($order['toPay'] == 0 && $order['toShip'] == 0 && $order['toReceive'] == 0 && $order['toRate'] == 0) {
                                                        $status = 'Cancelled';
                                                        $status_class = 'bg-secondary';
                                                    } else {
                                                        $status = 'Processing';
                                                        $status_class = 'bg-info';
                                                    }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo $status; ?></span>
                                                
                                                <!-- Progress tracker -->
                                                <div class="progress mt-2" style="height: 5px;">
                                                    <?php
                                                        $progress = 0;
                                                        if (!$order['toPay']) $progress += 25;
                                                        if (!$order['toShip']) $progress += 25;
                                                        if (!$order['toReceive']) $progress += 25;
                                                        if (!$order['toRate']) $progress += 25;
                                                    ?>
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progress; ?>%" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-2">
                                                    <a href="details.php?id=<?php echo $order['orderId']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye me-1"></i> Details
                                                    </a>
                                                    
                                                    <?php if ($order['toRate']): ?>
                                                    <a href="rate.php?id=<?php echo $order['orderId']; ?>" class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-star me-1"></i> Rate
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($order['toReceive']): ?>
                                                    <a href="receive.php?id=<?php echo $order['orderId']; ?>" class="btn btn-sm btn-outline-info">
                                                        <i class="bi bi-check-circle me-1"></i> Confirm Receipt
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>