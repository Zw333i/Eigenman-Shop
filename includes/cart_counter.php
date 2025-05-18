<?php
// includes/cart_counter.php

function getCartItemCount($conn, $userId) {
    $count = 0;
    
    if (!empty($userId)) {
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM UserCart WHERE userId = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $count = $row['total'] ?: 0;
        }
        
        $stmt->close();
    }
    
    return $count;
}

$cartCount = 0;
if (isset($_SESSION['userId']) && $_SESSION['role'] === 'customer') {
    $cartCount = getCartItemCount($conn, $_SESSION['userId']);
}
?>

<!--
<li class="nav-item">
    <a class="nav-link position-relative" href="/e-commerce/cart/view.php">
        <i class="bi bi-cart"></i> Cart
        <?php if ($cartCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?php echo $cartCount; ?>
                <span class="visually-hidden">items in cart</span>
            </span>
        <?php endif; ?>
    </a>
</li>
-->