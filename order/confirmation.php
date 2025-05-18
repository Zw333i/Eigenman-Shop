<?php
// order/confirmation.php
session_start();

require_once "../config/database.php";
require_once "../includes/functions.php";

if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

$userId = $_SESSION['userId'];
?>

<?php include_once "../includes/header.php"; ?>
<?php include_once "../includes/navbar.php"; ?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card order-confirmation">
                <div class="card-body text-center p-5">
                    <div class="confirmation-icon mb-4">
                        <i class="fas fa-check-circle fa-5x text-success"></i>
                    </div>
                    
                    <h2 class="mb-4">Order Placed Successfully!</h2>
                    <p class="lead">Thank you for shopping with us.</p>
                    <p>Your order has been received and is now being processed.</p>
                    <p>You will receive order updates via notifications and email.</p>
                    
                    <div class="mt-5">
                        <a href="history.php" class="btn btn-primary me-2">
                            <i class="fas fa-list"></i> View My Orders
                        </a>
                        <a href="../product/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-shopping-bag"></i> Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once "../includes/footer.php"; ?>