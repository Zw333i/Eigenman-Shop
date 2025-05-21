<?php
// merchant/dashboard.php


session_start();

if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'merchant') {
    header("Location: /e-commerce/auth/login.php");
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/config/database.php';

$userId = $_SESSION['userId'];

$followersQuery = "SELECT followers FROM User WHERE userId = $userId";
$followersResult = $conn->query($followersQuery);
$followersCount = 0;

if ($followersResult && $followersResult->num_rows > 0) {
    $row = $followersResult->fetch_assoc();
    $followersCount = $row['followers'] ?? 0;
}

$merchantQuery = "SELECT * FROM Merchant WHERE userId = $userId";
$merchantResult = $conn->query($merchantQuery);
$merchantData = null;

if ($merchantResult && $merchantResult->num_rows > 0) {
    $merchantData = $merchantResult->fetch_assoc();
} else {
    $storeName = $_SESSION['firstname'] . "'s Store"; 
    $createMerchantQuery = "INSERT INTO Merchant (userId, storeName) VALUES ($userId, '$storeName')";
    if ($conn->query($createMerchantQuery)) {
        $merchantQuery = "SELECT * FROM Merchant WHERE userId = $userId";
        $merchantResult = $conn->query($merchantQuery);
        if ($merchantResult && $merchantResult->num_rows > 0) {
            $merchantData = $merchantResult->fetch_assoc();
        }
    }
}

$productCountQuery = "SELECT COUNT(*) as count FROM Item WHERE merchantId = $userId";
$productCountResult = $conn->query($productCountQuery);
$productCount = 0;
if ($productCountResult && $productCountResult->num_rows > 0) {
    $row = $productCountResult->fetch_assoc();
    $productCount = $row['count'];
}

$orderCountQuery = "SELECT COUNT(*) as count FROM Orders WHERE merchantId = $userId";
$orderCountResult = $conn->query($orderCountQuery);
$orderCount = 0;
if ($orderCountResult && $orderCountResult->num_rows > 0) {
    $row = $orderCountResult->fetch_assoc();
    $orderCount = $row['count'];
}

$pendingOrdersQuery = "SELECT COUNT(*) as count FROM Orders WHERE merchantId = $userId AND toShip = 1";
$pendingOrdersResult = $conn->query($pendingOrdersQuery);
$pendingOrders = 0;
if ($pendingOrdersResult && $pendingOrdersResult->num_rows > 0) {
    $row = $pendingOrdersResult->fetch_assoc();
    $pendingOrders = $row['count'];
}

// Updated total sales query to use COALESCE and ensure we get 0 when there are no sales
$totalSalesQuery = "SELECT COALESCE(SUM(totalPrice), 0) as total FROM Orders WHERE merchantId = $userId";
$totalSalesResult = $conn->query($totalSalesQuery);
$totalSales = 0;
if ($totalSalesResult && $totalSalesResult->num_rows > 0) {
    $row = $totalSalesResult->fetch_assoc();
    $totalSales = $row['total'];
}

$recentOrdersQuery = "SELECT o.*, i.itemName, u.firstname, u.lastname 
                     FROM Orders o
                     INNER JOIN Item i ON o.itemId = i.itemId
                     INNER JOIN User u ON o.userId = u.userId
                     WHERE o.merchantId = $userId
                     ORDER BY o.orderDate DESC
                     LIMIT 5";
$recentOrdersResult = $conn->query($recentOrdersQuery);

$pageTitle = "Merchant Dashboard";
include_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/header.php';
?>

<div class="merchant-dashboard">
    <div class="container py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/merchant/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9">
                <div class="welcome-section mb-4 p-4 bg-white rounded shadow-sm">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['firstname']); ?>!</h1>
                            <p class="text-muted"><?php echo htmlspecialchars($merchantData['storeName'] ?? "Your Store"); ?></p>
                        </div>
                        <a href="/e-commerce/merchant/products.php?action=add" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add New Product
                        </a>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-box text-primary mb-2" style="font-size: 2rem;"></i>
                                <h5 class="stat-value"><?php echo $productCount; ?></h5>
                                <p class="stat-label">Products</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-shopping-bag text-success mb-2" style="font-size: 2rem;"></i>
                                <h5 class="stat-value"><?php echo $orderCount; ?></h5>
                                <p class="stat-label">Orders</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-users text-info mb-2" style="font-size: 2rem;"></i>
                                <h5 class="stat-value"><?php echo $followersCount; ?></h5>
                                <p class="stat-label">Followers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="/e-commerce/merchant/sales_report.php" class="text-decoration-none">
                            <div class="card h-100 shadow-sm sales-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-peso-sign text-warning mb-2" style="font-size: 2rem;"></i>
                                    <h5 class="stat-value">₱<?php echo number_format($totalSales, 2); ?></h5>
                                    <p class="stat-label">Total Sales</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                
                <!-- Pending Orders Alert -->
                <?php if ($pendingOrders > 0): ?>
                <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <div>
                        You have <strong><?php echo $pendingOrders; ?></strong> pending orders to ship!
                        <a href="/e-commerce/merchant/orders.php?filter=pending" class="alert-link ms-2">View now</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Recent Orders -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Orders</h5>
                            <a href="/e-commerce/merchant/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($recentOrdersResult && $recentOrdersResult->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Order ID</th>
                                        <th scope="col">Customer</th>
                                        <th scope="col">Product</th>
                                        <th scope="col">Amount</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = $recentOrdersResult->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $order['orderId']; ?></td>
                                        <td><?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname']); ?></td>
                                        <td><?php echo htmlspecialchars($order['itemName']); ?></td>
                                        <td>₱<?php echo number_format($order['totalPrice'], 2); ?></td>
                                        <td>
                                            <?php
                                            if ($order['toShip']) {
                                                echo '<span class="badge bg-warning">To Ship</span>';
                                            } elseif ($order['toReceive']) {
                                                echo '<span class="badge bg-info">Shipped</span>';
                                            } elseif ($order['toRate']) {
                                                echo '<span class="badge bg-success">Delivered</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary">Completed</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($order['orderDate'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-shopping-bag text-muted mb-3" style="font-size: 3rem;"></i>
                            <h5>No orders yet</h5>
                            <p class="text-muted">You haven't received any orders yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">Manage Products</h5>
                                <p class="card-text text-muted">Add, edit or remove your products from the store.</p>
                                <a href="/e-commerce/merchant/products.php" class="btn btn-outline-primary">Go to Products</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title">View Orders</h5>
                                <p class="card-text text-muted">Change the statuses of your orders!</p>
                                <a href="/e-commerce/merchant/orders.php" class="btn btn-outline-primary">Go to Orders</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.merchant-dashboard {
    background-color: #f5f7fb;
    min-height: calc(100vh - 80px);
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 600;
    margin: 10px 0 5px;
}

.stat-label {
    font-size: 0.9rem;
    color: #6c757d;
    margin: 0;
}

.table th, .table td {
    padding: 1rem;
    vertical-align: middle;
}

.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

.sales-card {
    transition: all 0.3s ease;
}

.sales-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
</style>

<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/footer.php';
?>