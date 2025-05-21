<?php
// merchant/orders.php
session_start();

if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'merchant') {
    header("Location: /e-commerce/auth/login.php");
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/config/database.php';

$userId = $_SESSION['userId'];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = intval($_POST['orderId']);
    $status = $conn->real_escape_string($_POST['status']);
    
    $checkOrderQuery = "SELECT * FROM Orders WHERE orderId = $orderId AND merchantId = $userId";
    $checkResult = $conn->query($checkOrderQuery);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        $orderData = $checkResult->fetch_assoc();
        
        $updateSql = "";
        if ($status === 'shipped') {
            $updateSql = "UPDATE Orders SET toShip = 0, toReceive = 1, eta = DATE_ADD(NOW(), INTERVAL 3 DAY) WHERE orderId = $orderId";
        } elseif ($status === 'delivered') {
            $updateSql = "UPDATE Orders SET toReceive = 0, toRate = 1 WHERE orderId = $orderId";
        } elseif ($status === 'completed') {
            $updateSql = "UPDATE Orders SET toRate = 0 WHERE orderId = $orderId";
        }
        
        if (!empty($updateSql) && $conn->query($updateSql) === TRUE) {
            $customerId = $orderData['userId'];
            $notificationMessage = "";
            
            if ($status === 'shipped') {
                $notificationMessage = "Your order #$orderId has been shipped and is on its way!";
            } elseif ($status === 'delivered') {
                $notificationMessage = "Your order #$orderId has been marked as delivered. Please rate your purchase!";
            } elseif ($status === 'completed') {
                $notificationMessage = "Thank you for your purchase! Order #$orderId is now completed.";
            }
            
            if (!empty($notificationMessage)) {
                $insertNotifSql = "INSERT INTO Notifications (receiverId, senderId, orderId, message, timestamp) 
                                  VALUES ($customerId, $userId, $orderId, '$notificationMessage', NOW())";
                $conn->query($insertNotifSql);
            }
            
            $success_message = "Order #$orderId status has been updated to $status!";
        } else {
            $error_message = "Error updating order status: " . $conn->error;
        }
    } else {
        $error_message = "You don't have permission to update this order.";
    }
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$filterCondition = '';
if ($filter === 'pending') {
    $filterCondition = ' AND o.toShip = 1';
} elseif ($filter === 'shipped') {
    $filterCondition = ' AND o.toShip = 0 AND o.toReceive = 1';
} elseif ($filter === 'delivered') {
    $filterCondition = ' AND o.toReceive = 0 AND o.toRate = 1';
} elseif ($filter === 'completed') {
    $filterCondition = ' AND o.toShip = 0 AND o.toReceive = 0 AND o.toRate = 0';
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$countQuery = "SELECT COUNT(*) as total FROM Orders o WHERE o.merchantId = $userId $filterCondition";
$countResult = $conn->query($countQuery);
$totalOrders = 0;
if ($countResult && $countResult->num_rows > 0) {
    $row = $countResult->fetch_assoc();
    $totalOrders = $row['total'];
}
$totalPages = ceil($totalOrders / $limit);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$orderQuery = "SELECT o.*, i.itemName, i.picture, u.firstname, u.lastname, u.contactNum 
               FROM Orders o
               INNER JOIN Item i ON o.itemId = i.itemId
               INNER JOIN User u ON o.userId = u.userId
               WHERE o.merchantId = $userId $filterCondition
               ORDER BY o.orderDate DESC
               LIMIT $limit OFFSET $offset";
$orderResult = $conn->query($orderQuery);

if (!$orderResult) {
    $error_message = "Database error: " . $conn->error;
}

// Store the orders in an array for later use in modals
$orders = [];
if ($orderResult && $orderResult->num_rows > 0) {
    while ($order = $orderResult->fetch_assoc()) {
        $orders[] = $order;
    }
    // Reset the result pointer for the main table display
    $orderResult->data_seek(0);
}

$pageTitle = "Manage Orders";
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
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2 mb-0">Manage Orders</h1>
                </div>
                
                <!-- Alert Messages -->
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Order Filters -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div class="d-flex flex-wrap mb-3 mb-md-0">
                                <a href="/e-commerce/merchant/orders.php" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?> me-2 mb-2">
                                    All Orders
                                </a>
                                <a href="/e-commerce/merchant/orders.php?filter=pending" class="btn <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-outline-primary'; ?> me-2 mb-2">
                                    <i class="fas fa-box me-1"></i> Pending
                                </a>
                                <a href="/e-commerce/merchant/orders.php?filter=shipped" class="btn <?php echo $filter === 'shipped' ? 'btn-primary' : 'btn-outline-primary'; ?> me-2 mb-2">
                                    <i class="fas fa-shipping-fast me-1"></i> Shipped
                                </a>
                                <a href="/e-commerce/merchant/orders.php?filter=delivered" class="btn <?php echo $filter === 'delivered' ? 'btn-primary' : 'btn-outline-primary'; ?> me-2 mb-2">
                                    <i class="fas fa-check-circle me-1"></i> Delivered
                                </a>
                                <a href="/e-commerce/merchant/orders.php?filter=completed" class="btn <?php echo $filter === 'completed' ? 'btn-primary' : 'btn-outline-primary'; ?> mb-2">
                                    <i class="fas fa-flag-checkered me-1"></i> Completed
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Orders List -->
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <?php if ($orderResult && $orderResult->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Order ID</th>
                                        <th scope="col">Customer</th>
                                        <th scope="col">Product</th>
                                        <th scope="col">Quantity</th>
                                        <th scope="col">Total</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = $orderResult->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?php echo $order['orderId']; ?></td>
                                        <td><?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($order['picture'])): ?>
                                                <img src="/e-commerce/<?php echo htmlspecialchars($order['picture']); ?>" 
                                                     alt="<?php echo htmlspecialchars($order['itemName']); ?>" 
                                                     class="img-thumbnail me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                <?php else: ?>
                                                <div class="bg-light d-flex align-items-center justify-content-center me-2" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                                <?php endif; ?>
                                                <span><?php echo htmlspecialchars($order['itemName']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo $order['quantity']; ?></td>
                                        <td>₱<?php echo number_format($order['totalPrice'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['orderDate'])); ?></td>
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
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#orderDetailsModal<?php echo $order['orderId']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div class="d-flex justify-content-center p-3">
                            <nav aria-label="Page navigation">
                                <ul class="pagination mb-0">
                                    <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="/e-commerce/merchant/orders.php?<?php echo $filter !== 'all' ? 'filter='.$filter.'&' : ''; ?>page=<?php echo $page - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="/e-commerce/merchant/orders.php?<?php echo $filter !== 'all' ? 'filter='.$filter.'&' : ''; ?>page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="/e-commerce/merchant/orders.php?<?php echo $filter !== 'all' ? 'filter='.$filter.'&' : ''; ?>page=<?php echo $page + 1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-box-open text-muted mb-3" style="font-size: 3rem;"></i>
                            <h5>No orders found</h5>
                            <p class="text-muted">
                                <?php
                                if ($filter === 'pending') {
                                    echo "You don't have any pending orders to ship.";
                                } elseif ($filter === 'shipped') {
                                    echo "You don't have any shipped orders being delivered.";
                                } elseif ($filter === 'delivered') {
                                    echo "You don't have any delivered orders waiting for rating.";
                                } elseif ($filter === 'completed') {
                                    echo "You don't have any completed orders.";
                                } else {
                                    echo "You haven't received any orders yet.";
                                }
                                ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Order Details Modals -->
<?php
// Use the stored orders array to generate modals
if (!empty($orders)):
    foreach ($orders as $order):
?>
<div class="modal fade" id="orderDetailsModal<?php echo $order['orderId']; ?>" tabindex="-1" 
     aria-labelledby="orderDetailsModalLabel<?php echo $order['orderId']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderDetailsModalLabel<?php echo $order['orderId']; ?>">
                    Order #<?php echo $order['orderId']; ?> Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Order Information</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Order Date:</strong></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($order['orderDate'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Product:</strong></td>
                                <td><?php echo htmlspecialchars($order['itemName']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Quantity:</strong></td>
                                <td><?php echo $order['quantity']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Price:</strong></td>
                                <td>₱<?php echo number_format($order['totalPrice'], 2); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Payment Method:</strong></td>
                                <td><?php echo htmlspecialchars($order['modeOfPayment']); ?></td>
                            </tr>
                            <?php if (!empty($order['eta'])): ?>
                            <tr>
                                <td><strong>ETA:</strong></td>
                                <td><?php echo date('M d, Y', strtotime($order['eta'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Customer Information</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td><?php echo htmlspecialchars($order['firstname'] . ' ' . $order['lastname']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Contact:</strong></td>
                                <td><?php echo htmlspecialchars($order['contactNum']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Delivery Address:</strong></td>
                                <td><?php echo htmlspecialchars($order['address']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-12">
                        <h6 class="fw-bold">Order Status</h6>
                        <div class="progress-track">
                            <ul class="progressbar">
                                <li class="active">Order Placed</li>
                                <li class="<?php echo !$order['toShip'] ? 'active' : ''; ?>">Shipped</li>
                                <li class="<?php echo !$order['toReceive'] && !$order['toShip'] ? 'active' : ''; ?>">Delivered</li>
                                <li class="<?php echo !$order['toRate'] && !$order['toReceive'] && !$order['toShip'] ? 'active' : ''; ?>">Completed</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-12">
                        <h6 class="fw-bold">Update Status</h6>
                        <form method="POST" action="/e-commerce/merchant/orders.php<?php echo $filter !== 'all' ? '?filter='.$filter : ''; ?><?php echo isset($_GET['page']) ? ($filter !== 'all' ? '&' : '?') . 'page='.$_GET['page'] : ''; ?>">
                            <input type="hidden" name="orderId" value="<?php echo $order['orderId']; ?>">
                            <div class="mb-3">
                                <select name="status" class="form-select" required>
                                    <option value="" selected disabled>Select new status</option>
                                    <?php if ($order['toShip']): ?>
                                    <option value="shipped">Mark as Shipped</option>
                                    <?php elseif ($order['toReceive']): ?>
                                    <option value="delivered">Mark as Delivered</option>
                                    <?php elseif ($order['toRate']): ?>
                                    <option value="completed">Mark as Completed</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    endforeach;
endif;
?>

<style>
.merchant-dashboard {
    background-color: #f5f7fb;
    min-height: calc(100vh - 80px);
}

.table th, .table td {
    padding: 1rem;
    vertical-align: middle;
}

.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

/* Order Progress Tracker */
.progress-track {
    margin-top: 15px;
    margin-bottom: 15px;
    position: relative;
    padding-left: 0;
    overflow: hidden;
}

.progressbar {
    counter-reset: step;
    padding-left: 0;
}

.progressbar li {
    list-style-type: none;
    width: 25%;
    float: left;
    font-size: 12px;
    position: relative;
    text-align: center;
    color: #7d7d7d;
}

.progressbar li:before {
    content: counter(step);
    counter-increment: step;
    width: 30px;
    height: 30px;
    line-height: 30px;
    border: 1px solid #ddd;
    display: block;
    text-align: center;
    margin: 0 auto 10px auto;
    border-radius: 50%;
    background-color: white;
    z-index: 1;
    position: relative;
}

.progressbar li:after {
    content: '';
    position: absolute;
    width: 100%;
    height: 1px;
    background-color: #ddd;
    top: 15px;
    left: -50%;
    z-index: 0;
}

.progressbar li:first-child:after {
    content: none;
}

.progressbar li.active {
    color: #0d6efd;
}

.progressbar li.active:before {
    border-color: #0d6efd;
    background-color: #0d6efd;
    color: white;
}

.progressbar li.active + li:after {
    background-color: #0d6efd;
}
</style>

<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/footer.php';
?>