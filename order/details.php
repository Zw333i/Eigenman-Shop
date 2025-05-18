<?php
session_start();
require_once '../includes/functions.php';

// order/details.php
requireLogin();

$title = "Order Details";
$user = getCurrentUser();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: history.php");
    exit;
}

$order_id = $_GET['id'];

if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['userId'];

$conn = getConnection();

$stmt = $conn->prepare("SELECT * FROM Orders WHERE orderId = ? AND userId = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: history.php");
    exit;
}

$order = $result->fetch_assoc();

$stmt = $conn->prepare("
    SELECT i.*, m.storeName
    FROM Item i 
    JOIN Merchant m ON i.merchantId = m.merchantId
    WHERE i.itemId = ?
");
$stmt->bind_param("i", $order['itemId']);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();

$orderStatus = 'Processing';
$statusClass = 'badge bg-indigo text-white'; 

if ($order['toPay']) {
    $orderStatus = 'To Pay';
    $statusClass = 'badge bg-danger text-white';
} elseif ($order['toShip']) {
    $orderStatus = 'To Ship';
    $statusClass = 'badge bg-warning text-dark'; 
} elseif ($order['toReceive']) {
    $orderStatus = 'To Receive';
    $statusClass = 'badge bg-info text-dark';
} elseif ($order['toRate']) {
    $orderStatus = 'To Rate';
    $statusClass = 'badge bg-purple text-white'; 
} else {
    $orderStatus = 'Completed';
    $statusClass = 'badge bg-success text-white';
}

$isCompletedAndRated = !$order['toPay'] && !$order['toShip'] && !$order['toReceive'] && !$order['toRate'] && 
                    isset($order['rating']) && $order['rating'] > 0;

function formatPHP($price) {
    return '₱' . number_format($price, 2);
}

function generateRatingStars($rating) {
    $stars = '';
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $fullStars) {
            $stars .= '<i class="bi bi-star-fill text-warning"></i>';
        } elseif ($i == $fullStars + 1 && $halfStar) {
            $stars .= '<i class="bi bi-star-half text-warning"></i>';
        } else {
            $stars .= '<i class="bi bi-star text-warning"></i>';
        }
    }
    
    return $stars;
}

include_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none text-primary">Home</a></li>
                    <li class="breadcrumb-item"><a href="history.php" class="text-decoration-none text-primary">Order History</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Order #<?php echo $order['orderId']; ?></li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Order Details Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center" style="border-bottom: 2px solid #0d6efd;">
                    <h5 class="mb-0 text-primary">Order #<?php echo $order['orderId']; ?></h5>
                    <span class="badge bg-light text-dark">
                        <?php echo date('M d, Y', strtotime($order['orderDate'])); ?>
                    </span>
                </div>
                <div class="card-body">
                    <!-- Order Status -->
                    <div class="mb-4">
                        <h6 class="card-subtitle mb-2 text-muted">Order Status</h6>
                        <span class="<?php echo $statusClass; ?> px-3 py-2">
                            <?php echo $orderStatus; ?>
                        </span>
                        
                        <!-- Enhanced Progress Tracker -->
                        <div class="mt-4">
                            <div class="d-flex justify-content-between position-relative mb-3">
                                <!-- Progress line -->
                                <div class="progress position-absolute w-100" style="height: 6px; top: 17px; z-index: 0;">
                                    <div class="progress-bar bg-primary" role="progressbar" 
                                         style="width: <?php 
                                             $progress = 0;
                                             if (!$order['toPay']) $progress += 25;
                                             if (!$order['toShip']) $progress += 25;
                                             if (!$order['toReceive']) $progress += 25;
                                             if (!$order['toRate']) $progress += 25;
                                             echo $progress; 
                                         ?>%" 
                                         aria-valuenow="<?php echo $progress; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
        
                                    <!-- Status steps -->
                                <div class="d-flex flex-column align-items-center position-relative" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm" 
                                         style="width: 40px; height: 40px; <?php echo !$order['toPay'] ? 'background-color: #dc3545;' : 'background-color: #f1f1f1; border: 2px solid #dc3545;'; ?>">
                                        <?php if (!$order['toPay']): ?>
                                            <i class="bi bi-check-lg text-white"></i>
                                        <?php else: ?>
                                            <span class="text-danger">1</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="mt-2 small fw-bold <?php echo !$order['toPay'] ? 'text-danger' : 'text-muted'; ?>">Payment</span>
                                </div>
                                
                                <div class="d-flex flex-column align-items-center position-relative" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm" 
                                         style="width: 40px; height: 40px; <?php echo !$order['toShip'] ? 'background-color: #fd7e14;' : 'background-color: #f1f1f1; border: 2px solid #fd7e14;'; ?>">
                                        <?php if (!$order['toShip']): ?>
                                            <i class="bi bi-check-lg text-white"></i>
                                        <?php else: ?>
                                            <span class="text-warning">2</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="mt-2 small fw-bold <?php echo !$order['toShip'] ? 'text-warning' : 'text-muted'; ?>">Shipping</span>
                                </div>
                                
                                <div class="d-flex flex-column align-items-center position-relative" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm" 
                                         style="width: 40px; height: 40px; <?php echo !$order['toReceive'] ? 'background-color: #0dcaf0;' : 'background-color: #f1f1f1; border: 2px solid #0dcaf0;'; ?>">
                                        <?php if (!$order['toReceive']): ?>
                                            <i class="bi bi-check-lg text-white"></i>
                                        <?php else: ?>
                                            <span class="text-info">3</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="mt-2 small fw-bold <?php echo !$order['toReceive'] ? 'text-info' : 'text-muted'; ?>">Delivery</span>
                                </div>
                                
                                <div class="d-flex flex-column align-items-center position-relative" style="z-index: 1;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm" 
                                         style="width: 40px; height: 40px; <?php echo !$order['toRate'] ? 'background-color: #6f42c1;' : 'background-color: #f1f1f1; border: 2px solid #6f42c1;'; ?>">
                                        <?php if (!$order['toRate']): ?>
                                            <i class="bi bi-check-lg text-white"></i>
                                        <?php else: ?>
                                            <span class="text-purple">4</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="mt-2 small fw-bold <?php echo !$order['toRate'] ? 'text-purple' : 'text-muted'; ?>">Complete</span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($order['eta'])): ?>
                            <div class="mt-3">
                                <strong>Estimated Delivery:</strong> 
                                <span class="text-primary"><?php echo date('M d, Y', strtotime($order['eta'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Item Details -->
                    <div class="mb-4">
                        <h6 class="card-subtitle mb-3 text-muted">Item Details</h6>
                        <div class="border rounded p-3">
                            <div class="row align-items-center">
                                <div class="col-md-2 col-sm-3 mb-3 mb-md-0">
                                    <?php if (!empty($item['picture'])): ?>
                                        <img src="../<?php echo htmlspecialchars($item['picture']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['itemName']); ?>" 
                                             class="img-fluid rounded" style="max-height: 80px; width: auto;">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center rounded" style="height: 80px;">
                                            <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-7 col-sm-9">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['itemName']); ?></h6>
                                    <p class="mb-1 text-muted small">Brand: <?php echo htmlspecialchars($item['brand']); ?></p>
                                    <p class="mb-0 text-muted small">Store: <?php echo htmlspecialchars($item['storeName']); ?></p>
                                </div>
                                <div class="col-md-3 mt-3 mt-md-0 text-md-end">
                                    <div class="mb-1"><?php echo formatPHP($item['itemPrice']); ?> × <?php echo $order['quantity']; ?></div>
                                    <div class="fw-bold text-primary"><?php echo formatPHP($order['totalPrice']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rating and Review Section (Only visible when completed and rated) -->
                    <?php if ($isCompletedAndRated): ?>
                    <div class="mb-4 animate__animated animate__fadeIn">
                        <h6 class="card-subtitle mb-3 text-muted">Your Rating & Review</h6>
                        <div class="border rounded p-3 bg-light">
                            <div class="mb-2">
                                <div class="d-flex align-items-center mb-1">
                                    <div class="me-2 d-flex">
                                        <?php echo generateRatingStars($order['rating']); ?>
                                    </div>
                                    <span class="fw-bold"><?php echo number_format($order['rating'], 1); ?>/5</span>
                                </div>
                                <div class="small text-muted">
                                    Rated on <?php echo date('M d, Y', strtotime($order['ratingDate'])); ?>
                                </div>
                            </div>
                            <?php if (!empty($order['review'])): ?>
                            <div class="border-top pt-3 mt-3">
                                <div class="mb-2 fw-semibold">Your Review:</div>
                                <div class="fst-italic">
                                    "<?php echo nl2br(htmlspecialchars($order['review'])); ?>"
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div>
                        <h6 class="card-subtitle mb-3 text-muted">Order Summary</h6>
                        <div class="border rounded p-3">
                            <div class="row mb-2">
                                <div class="col-6">Subtotal</div>
                                <div class="col-6 text-end"><?php echo formatPHP($item['itemPrice'] * $order['quantity']); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6">Shipping Fee</div>
                                <div class="col-6 text-end">
                                    <?php 
                                    $shippingFee = $order['totalPrice'] - ($item['itemPrice'] * $order['quantity']);
                                    echo formatPHP($shippingFee >= 0 ? $shippingFee : 0); 
                                    ?>
                                </div>
                            </div>
                            <hr>
                            <div class="row fw-bold">
                                <div class="col-6">Total</div>
                                <div class="col-6 text-end text-primary"><?php echo formatPHP($order['totalPrice']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Shipping Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3" style="border-bottom: 2px solid #0d6efd;">
                    <h5 class="mb-0 text-primary">Shipping Information</h5>
                </div>
                <div class="card-body">
                    <address class="mb-0">
                        <strong><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></strong><br>
                        <?php echo htmlspecialchars($order['address']); ?><br>
                        <abbr title="Phone"></abbr> <?php echo htmlspecialchars($order['contact']); ?>
                    </address>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3" style="border-bottom: 2px solid #0d6efd;">
                    <h5 class="mb-0 text-primary">Payment Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Payment Method:</strong> <?php echo ucfirst(htmlspecialchars($order['modeOfPayment'])); ?>
                    </div>
                    <div>
                        <strong>Payment Status:</strong> 
                        <?php if (!$order['toPay']): ?>
                            <span class="badge bg-primary">Paid</span>
                        <?php else: ?>
                            <span class="badge bg-danger text-white">Pending</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3" style="border-bottom: 2px solid #0d6efd;">
                    <h5 class="mb-0 text-primary">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="history.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left me-1"></i> Back to Order History
                        </a>
                        
                        <?php if ($order['toReceive']): ?>
                        <a href="receive.php?id=<?php echo $order['orderId']; ?>" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i> Confirm Receipt
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($order['toRate']): ?>
                        <a href="rate.php?id=<?php echo $order['orderId']; ?>" class="btn btn-primary">
                            <i class="bi bi-star me-1"></i> Rate Product
                        </a>
                        <?php endif; ?>
                        
        
                        
                        <?php if ($order['toPay']): ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal">
                            <i class="bi bi-credit-card me-1"></i> Pay Now
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($order['toPay'] || $order['toShip']): ?>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                            <i class="bi bi-x-circle me-1"></i> Cancel Order
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($order['toPay']): ?>
<!-- Payment Confirmation Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary bg-gradient text-white border-0">
                <h5 class="modal-title fw-bold" id="paymentModalLabel">
                    <i class="bi bi-credit-card me-2"></i>Confirm Payment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info bg-info-subtle border-info-subtle mb-4 animate__animated animate__fadeIn">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="bi bi-info-circle-fill fs-4"></i>
                        </div>
                        <div>
                            <p class="mb-0">Review your order details before confirming payment!</p>
                        </div>
                    </div>
                </div>
                
                <div class="card border-0 bg-light mb-4 animate__animated animate__fadeInUp animate__delay-1s">
                    <div class="card-body p-3">
                        <h6 class="card-subtitle mb-3 text-muted">Order Summary</h6>
                        <div class="row mb-2">
                            <div class="col-5 fw-semibold text-muted">Order ID:</div>
                            <div class="col-7 fw-bold">#<?php echo $order['orderId']; ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-semibold text-muted">Item:</div>
                            <div class="col-7"><?php echo htmlspecialchars($item['itemName']); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-semibold text-muted">Amount:</div>
                            <div class="col-7 fw-bold text-primary"><?php echo formatPHP($order['totalPrice']); ?></div>
                        </div>
                        <div class="row">
                            <div class="col-5 fw-semibold text-muted">Payment Method:</div>
                            <div class="col-7">
                                <span class="badge rounded-pill 
                                    <?php echo strtolower($order['modeOfPayment']) == 'credit card' ? 'bg-success' : 
                                    (strtolower($order['modeOfPayment']) == 'bank transfer' ? 'bg-info' : 'bg-secondary'); ?>">
                                    <?php 
                                    $paymentIcon = 'cash';
                                    switch(strtolower($order['modeOfPayment'])) {
                                        case 'credit card':
                                            $paymentIcon = 'credit-card';
                                            break;
                                        case 'bank transfer':
                                            $paymentIcon = 'bank';
                                            break;
                                        case 'paypal':
                                            $paymentIcon = 'paypal';
                                            break;
                                    }
                                    ?>
                                    <i class="bi bi-<?php echo $paymentIcon; ?> me-1"></i>
                                    <?php echo ucfirst(htmlspecialchars($order['modeOfPayment'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3 mb-2 animate__animated animate__fadeIn animate__delay-2s">
                    <i class="bi bi-shield-check text-success fs-1"></i>
                    <p class="small text-muted mt-2">This transaction is secured and encrypted</p>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center gap-2 p-3 animate__animated animate__fadeInUp animate__delay-2s">
                <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
                <form action="process_payment.php" method="post">
                    <input type="hidden" name="action" value="process_payment">
                    <input type="hidden" name="order_id" value="<?php echo $order['orderId']; ?>">
                    <button type="submit" class="btn btn-primary px-4 rounded-pill btn-confirmation position-relative">
                        <span class="position-relative">
                            <i class="bi bi-check-circle me-1"></i>Confirm Payment
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .modal.fade .modal-dialog {
        transform: scale(0.95);
        transition: transform 0.3s ease-in-out;
    }
    
    .modal.show .modal-dialog {
        transform: scale(1);
    }
    
    @keyframes softPulse {
        0% {
            box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.4);
        }
        70% {
            box-shadow: 0 0 0 8px rgba(13, 110, 253, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(13, 110, 253, 0);
        }
    }
    
    .btn-confirmation {
        transition: all 0.3s ease;
    }
    
    .btn-confirmation:hover {
        transform: translateY(-2px);
    }
    
    .modal.show .btn-confirmation {
        animation: softPulse 2s infinite;
        animation-delay: 3s;
    }
    
    .badge {
        transition: all 0.3s ease;
    }
    
    .badge:hover {
        transform: scale(1.05);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const paymentModal = document.getElementById('paymentModal');
        paymentModal.addEventListener('shown.bs.modal', function() {
            const rows = document.querySelectorAll('.row');
            rows.forEach((row, index) => {
                row.classList.add('animate__animated', 'animate__fadeInRight');
                row.style.animationDelay = `${0.5 + (index * 0.1)}s`;
            });
        });
        
        paymentModal.addEventListener('hidden.bs.modal', function() {
            const animatedElements = document.querySelectorAll('.animate__animated');
            animatedElements.forEach(el => {
                el.style.opacity = 0;
                setTimeout(() => {
                    el.style.opacity = 1;
                }, 500);
            });
        });
    });
</script>

<?php endif; ?>

<?php if ($order['toPay'] || $order['toShip']): ?>
<!-- Cancel Order pane -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="cancelOrderModalLabel">Cancel Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this order?</p>
                <p><strong>Order #:</strong> <?php echo $order['orderId']; ?></p>
                <p><strong>Item:</strong> <?php echo htmlspecialchars($item['itemName']); ?></p>
                <p><strong>Total:</strong> <?php echo formatPHP($order['totalPrice']); ?></p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">No, Keep Order</button>
                <form action="process.php" method="post">
                    <input type="hidden" name="action" value="cancel_order">
                    <input type="hidden" name="order_id" value="<?php echo $order['orderId']; ?>">
                    <button type="submit" class="btn btn-danger">Yes, Cancel Order</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include_once '../includes/footer.php'; ?>