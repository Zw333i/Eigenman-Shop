<?php
// cart/view.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$userId = $_SESSION['userId'];
$cartItems = [];
$totalCartValue = 0;
$totalItems = 0;

$query = "SELECT c.*, i.itemName, i.picture, i.brand, i.itemPrice, i.quantity as availableQuantity, 
          m.merchantId, m.storeName 
          FROM UserCart c 
          JOIN Item i ON c.itemId = i.itemId 
          JOIN Merchant m ON i.merchantId = m.merchantId 
          WHERE c.userId = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['unitPrice'] = $row['itemPrice']; 
    $row['totalPrice'] = $row['unitPrice'] * $row['quantity']; 
    
    $cartItems[] = $row;
    $totalCartValue += $row['totalPrice'];
    $totalItems += $row['quantity'];
}

$stmt->close();

$merchantGroupedItems = [];
foreach ($cartItems as $item) {
    $merchantId = $item['merchantId'];
    if (!isset($merchantGroupedItems[$merchantId])) {
        $merchantGroupedItems[$merchantId] = [
            'merchantId' => $merchantId,
            'storeName' => $item['storeName'],
            'items' => [],
            'subtotal' => 0,
            'itemCount' => 0
        ];
    }
    $merchantGroupedItems[$merchantId]['items'][] = $item;
    $merchantGroupedItems[$merchantId]['subtotal'] += $item['totalPrice'];
    $merchantGroupedItems[$merchantId]['itemCount'] += $item['quantity'];
}

$hasErrors = false;
foreach ($cartItems as $item) {
    if ($item['quantity'] <= 0 || $item['totalPrice'] <= 0) {
        $hasErrors = true;
        break;
    }
}

include_once '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<style>
    .cart-container {
        margin-top: 2rem;
        margin-bottom: 4rem;
    }
    
    .cart-header {
        padding-bottom: 1.5rem;
        margin-bottom: 2rem;
        border-bottom: 1px solid #e9ecef;
    }
    
    .cart-item {
        padding: 1.5rem;
        transition: all 0.3s ease-in-out;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .cart-item:hover {
        background-color: rgba(0,0,0,0.02);
        transform: translateY(-2px);
    }
    
    .cart-item img {
        object-fit: cover;
        height: 120px;
        width: 120px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }
    
    .cart-item img:hover {
        transform: scale(1.05);
    }
    
    .merchant-card {
        margin-bottom: 2rem;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        border: none;
        transition: all 0.3s ease;
    }
    
    .merchant-card:hover {
        box-shadow: 0 0.75rem 1.5rem rgba(0,0,0,0.15);
    }
    
    .merchant-header {
        padding: 1.25rem 1.5rem;
        background-color: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }
    
    .item-actions {
        display: flex;
        align-items: center;
    }
    
    .quantity-control {
        max-width: 140px;
        display: flex;
        align-items: center;
    }
    
    .quantity-input {
        text-align: center;
        font-weight: bold;
        height: 38px;
        background-color: #fff !important;
        border-color: #ced4da;
        width: 50px;
    }
    
    .quantity-btn {
        height: 38px;
        width: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-color: #ced4da;
        background-color: #f8f9fa;
        transition: all 0.2s;
    }
    
    .quantity-btn:hover {
        background-color: #e9ecef;
    }
    
    .quantity-btn:active {
        transform: scale(0.95);
    }
    
    .summary-card {
        position: sticky;
        top: 2rem;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        border: none;
        transition: all 0.3s ease;
    }
    
    .summary-card:hover {
        box-shadow: 0 0.75rem 1.5rem rgba(0,0,0,0.15);
    }
    
    .summary-header {
        padding: 1.25rem 1.5rem;
        background-color: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }
    
    .price-breakdown {
        border-radius: 12px;
        padding: 1.5rem;
        background-color: #f9f9f9;
        margin-bottom: 1.5rem;
    }
    
    .btn-checkout {
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        transition: all 0.3s ease-in-out;
        border-radius: 50px;
    }
    
    .btn-checkout:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,123,255,0.3);
    }
    
    .btn-continue {
        padding: 0.75rem 1.5rem;
        border-radius: 50px;
        transition: all 0.3s ease;
    }
    
    .btn-continue:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    .btn-remove {
        background: transparent;
        border: none;
        color: #dc3545;
        transition: all 0.2s ease;
        padding: 0.25rem 0.5rem;
        border-radius: 50px;
    }
    
    .btn-remove:hover {
        color: #bd2130;
        transform: scale(1.1);
        background-color: rgba(220, 53, 69, 0.1);
    }
    
    .notification-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background-color: #dc3545;
        color: white;
        border-radius: 50%;
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .alert {
        border-radius: 12px;
        border: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .store-link {
        color: inherit;
        text-decoration: none;
        transition: color 0.2s;
        font-weight: 600;
    }
    
    .store-link:hover {
        color: #007bff;
    }
    
    .empty-cart {
        text-align: center;
        padding: 5rem 0;
        background-color: #f9f9f9;
        border-radius: 15px;
        margin-top: 2rem;
    }
    
    .empty-cart i {
        font-size: 6rem;
        color: #d1d9e6;
        margin-bottom: 2rem;
    }
    
    .empty-cart .btn {
        border-radius: 50px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .empty-cart .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,123,255,0.3);
    }
    
    .store-badge {
        border-radius: 50px;
        padding: 0.5rem 1rem;
        font-weight: 600;
    }
    
    .update-btn {
        border-radius: 50%;
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: 0.5rem;
        transition: all 0.3s ease;
    }
    
    .update-btn:hover {
        transform: rotate(180deg);
    }
    
    .item-name {
        font-weight: 600;
        transition: all 0.2s ease;
    }
    
    .item-name:hover {
        color: #007bff;
    }
    
    .price-tag {
        background-color: #f0f8ff;
        border-radius: 50px;
        padding: 0.25rem 0.75rem;
        display: inline-block;
    }
    
    .select-all-container {
        padding: 1rem 1.5rem;
        background-color: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }
    
    .form-check-input {
        width: 1.2em;
        height: 1.2em;
        margin-top: 0.1em;
    }
    
    .form-check-label {
        margin-left: 0.5rem;
        font-weight: 500;
    }
    
    .item-checkbox {
        position: absolute;
        top: 1.5rem;
        left: 1.5rem;
        z-index: 1;
    }
    
    .item-checkbox .form-check-input {
        transform: scale(1.3);
    }
    
    .item-content {
        margin-left: 2rem;
    }
    
    @media (max-width: 768px) {
        .cart-item img {
            height: 100px;
            width: 100px;
        }
        
        .summary-card {
            position: static;
            margin-top: 1.5rem;
        }
        
        .item-checkbox {
            top: 1rem;
            left: 1rem;
        }
    }
</style>

<div class="container cart-container">
    <div class="row">
        <div class="col-12">
            <div class="cart-header d-flex justify-content-between align-items-center">
                <h1 class="mb-0">Your Shopping Cart <?php if($totalItems > 0): ?><span class="text-muted fs-5">(<?php echo $totalItems; ?> items)</span><?php endif; ?></h1>
                <a href="../product/index.php" class="btn btn-outline-primary btn-continue">
                    <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                </a>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($hasErrors): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    There might be issues with some items in your cart. Please check quantities and prices.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (empty($cartItems)): ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
            <h3>Your cart is empty</h3>
            <p class="text-muted mb-4">Looks like you haven't added any products to your cart yet.</p>
            <a href="../product/index.php" class="btn btn-primary btn-lg">
                <i class="fas fa-shopping-bag me-2"></i> Start Shopping
            </a>
        </div>
    <?php else: ?>
        <form id="checkoutForm" action="../order/checkout.php" method="post">
            <div class="row">
                <div class="col-lg-8 col-md-12 mb-4">
                    <div class="merchant-card">
                        <div class="select-all-container">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllItems">
                                <label class="form-check-label" for="selectAllItems">
                                    Select all items
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <?php foreach ($merchantGroupedItems as $merchantGroup): ?>
                        <div class="merchant-card">
                            <div class="merchant-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <a href="../product/index.php?merchant=<?php echo $merchantGroup['merchantId']; ?>" class="store-link">
                                        <i class="fas fa-store me-2"></i>
                                        <?php echo htmlspecialchars($merchantGroup['storeName']); ?>
                                    </a>
                                </h5>
                                <span class="badge bg-light text-dark store-badge">
                                    <i class="fas fa-box me-1"></i>
                                    <?php echo $merchantGroup['itemCount']; ?> item<?php echo $merchantGroup['itemCount'] > 1 ? 's' : ''; ?>
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <?php foreach ($merchantGroup['items'] as $item): ?>
                                    <div class="cart-item position-relative">
                                        <div class="item-checkbox">
                                            <!-- In the cart item checkbox section, remove the "checked" attribute -->
                                            <div class="form-check">
                                                <input class="form-check-input item-checkbox-input" type="checkbox" 
                                                    name="selectedItems[]" 
                                                    value="<?php echo $item['itemId']; ?>" 
                                                    id="item-<?php echo $item['itemId']; ?>"
                                                    data-price="<?php echo $item['totalPrice']; ?>">
                                            </div>
                                                                                    </div>
                                        <div class="item-content">
                                            <div class="row align-items-center">
                                                <div class="col-md-2 col-4">
                                                    <a href="../product/details.php?id=<?php echo $item['itemId']; ?>">
                                                        <?php if (!empty($item['picture'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($item['picture']); ?>" class="img-fluid rounded" alt="<?php echo htmlspecialchars($item['itemName']); ?>">
                                                        <?php else: ?>
                                                            <img src="../assets/images/product-placeholder.jpg" class="img-fluid rounded" alt="Product Image">
                                                        <?php endif; ?>
                                                    </a>
                                                </div>
                                                <div class="col-md-5 col-8">
                                                    <h5 class="mb-2">
                                                        <a href="../product/details.php?id=<?php echo $item['itemId']; ?>" class="text-decoration-none text-dark item-name">
                                                            <?php echo htmlspecialchars($item['itemName']); ?>
                                                        </a>
                                                    </h5>
                                                    <p class="text-muted mb-2">
                                                        <i class="fas fa-tag me-1"></i>
                                                        <small><?php echo htmlspecialchars($item['brand']); ?></small>
                                                    </p>
                                                    <p class="mb-1">
                                                        <span class="price-tag">
                                                            <i class="fas fa-tag me-1"></i>
                                                            <span class="text-primary fw-bold">₱<?php echo number_format($item['unitPrice'], 2); ?></span>
                                                            <small class="text-muted">per item</small>
                                                        </span>
                                                    </p>
                                                    <p class="mb-0">
                                                        <small class="<?php echo ($item['availableQuantity'] < 10) ? 'text-danger' : 'text-success'; ?>">
                                                            <i class="fas <?php echo ($item['availableQuantity'] < 10) ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> me-1"></i>
                                                            <?php echo ($item['availableQuantity'] < 10) ? 'Only ' . $item['availableQuantity'] . ' left in stock' : 'In stock'; ?>
                                                        </small>
                                                    </p>
                                                </div>
                                                <div class="col-md-5 col-12 mt-3 mt-md-0">
                                                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                                                        <div class="quantity-control mb-3 mb-md-0">
                                                            <div class="d-flex quantity-form">
                                                                <input type="hidden" name="itemId" value="<?php echo $item['itemId']; ?>">
                                                                <button type="button" class="btn btn-outline-secondary quantity-btn decrease-btn" data-item-id="<?php echo $item['itemId']; ?>">
                                                                    <i class="fas fa-minus"></i>
                                                                </button>
                                                                <input type="text" name="quantity" id="quantity-<?php echo $item['itemId']; ?>" 
                                                                       value="<?php echo $item['quantity']; ?>" min="1" 
                                                                       max="<?php echo $item['availableQuantity']; ?>" 
                                                                       class="form-control quantity-input" 
                                                                       readonly>
                                                                <button type="button" class="btn btn-outline-secondary quantity-btn increase-btn" data-item-id="<?php echo $item['itemId']; ?>" data-max="<?php echo $item['availableQuantity']; ?>">
                                                                    <i class="fas fa-plus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="d-flex flex-column align-items-end">
                                                                <span class="fw-bold fs-5 item-total-price" data-item-id="<?php echo $item['itemId']; ?>">₱<?php echo number_format($item['totalPrice'], 2); ?></span>
                                                                <a href="update.php?itemId=<?php echo $item['itemId']; ?>&delete=1" class="btn-remove mt-2">
                                                                    <i class="fas fa-trash-alt me-1"></i> Remove
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="p-3 bg-light d-flex justify-content-between align-items-center">
                                    <span>Subtotal from this store</span>
                                    <span class="fw-bold fs-5">₱<?php echo number_format($merchantGroup['subtotal'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="col-lg-4 col-md-12">
                    <div class="summary-card">
                        <div class="summary-header">
                            <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="price-breakdown">
                                <div class="d-flex justify-content-between mb-3">
                                    <span><i class="fas fa-shopping-basket me-2"></i>Subtotal (<span id="selected-items-count"><?php echo $totalItems; ?></span> items)</span>
                                    <span class="fw-bold" id="selected-items-subtotal">₱<?php echo number_format($totalCartValue, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span><i class="fas fa-truck me-2"></i>Shipping Fee</span>
                                    <span>Calculated at checkout</span>
                                </div>
                                <?php if ($totalCartValue > 0): ?>
                                <div class="d-flex justify-content-between mb-3">
                                    <span><i class="fas fa-file-invoice-dollar me-2"></i>Tax</span>
                                    <span>Calculated at checkout</span>
                                </div>
                                <?php endif; ?>
                                <?php if ($totalCartValue >= 5000): ?>
                                <div class="d-flex justify-content-between text-success">
                                    <span><i class="fas fa-gift me-2"></i>Free Shipping</span>
                                    <span>-₱0.00</span>
                                </div>
                                <?php endif; ?>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Total</h5>
                                    <h4 class="text-primary mb-0" id="selected-items-total">₱<?php echo number_format($totalCartValue, 2); ?></h4>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-checkout">
                                    <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                                </button>
                                <a href="../product/index.php" class="btn btn-outline-secondary btn-continue">
                                    <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                                </a>
                            </div>
                            
                            <?php if ($totalCartValue >= 5000): ?>
                            <div class="alert alert-success mt-3 mb-0 text-center">
                                <i class="fas fa-truck me-2"></i>
                                Your order qualifies for FREE shipping!
                            </div>
                            <?php elseif ($totalCartValue > 0): ?>
                            <div class="alert alert-info mt-3 mb-0 text-center">
                                <i class="fas fa-info-circle me-2"></i>
                                Add ₱<?php echo number_format(5000 - $totalCartValue, 2); ?> more to qualify for FREE shipping!
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const decreaseBtns = document.querySelectorAll('.decrease-btn');
    const increaseBtns = document.querySelectorAll('.increase-btn');
    const selectAllCheckbox = document.getElementById('selectAllItems');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox-input');
    
    function updateCart(itemId, quantity) {
        fetch('update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `itemId=${itemId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const totalPriceElement = document.querySelector(`.item-total-price[data-item-id="${itemId}"]`);
                const unitPrice = parseFloat(data.unitPrice);
                const newTotalPrice = unitPrice * quantity;
                totalPriceElement.textContent = `₱${newTotalPrice.toFixed(2)}`;
                
                const checkbox = document.querySelector(`.item-checkbox-input[value="${itemId}"]`);
                if (checkbox) {
                    checkbox.dataset.price = newTotalPrice;
                }
                
                calculateSelectedTotal();
            } else {
                alert('Error updating cart: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating cart. Please try again.');
        });
    }
    
    function calculateSelectedTotal() {
        let selectedCount = 0;
        let subtotal = 0;
        
        itemCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                selectedCount++;
                subtotal += parseFloat(checkbox.dataset.price);
            }
        });
        
        document.getElementById('selected-items-count').textContent = selectedCount;
        document.getElementById('selected-items-subtotal').textContent = `₱${subtotal.toFixed(2)}`;
        document.getElementById('selected-items-total').textContent = `₱${subtotal.toFixed(2)}`;
        
        const freeShippingAlert = document.querySelector('.alert-success');
        const addMoreAlert = document.querySelector('.alert-info');
        
        if (subtotal >= 5000) {
            if (freeShippingAlert) {
                freeShippingAlert.style.display = 'block';
            }
            if (addMoreAlert) {
                addMoreAlert.style.display = 'none';
            }
        } else if (subtotal > 0) {
            const amountNeeded = 5000 - subtotal;
            if (addMoreAlert) {
                addMoreAlert.innerHTML = `<i class="fas fa-info-circle me-2"></i>Add ₱${amountNeeded.toFixed(2)} more to qualify for FREE shipping!`;
                addMoreAlert.style.display = 'block';
            }
            if (freeShippingAlert) {
                freeShippingAlert.style.display = 'none';
            }
        } else {
            if (freeShippingAlert) freeShippingAlert.style.display = 'none';
            if (addMoreAlert) addMoreAlert.style.display = 'none';
        }
    }
    
    decreaseBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = this.getAttribute('data-item-id');
            const input = document.getElementById('quantity-' + itemId);
            
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
                updateCart(itemId, input.value);
            }
        });
    });
    
    increaseBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = this.getAttribute('data-item-id');
            const maxQty = parseInt(this.getAttribute('data-max'));
            const input = document.getElementById('quantity-' + itemId);
            
            if (parseInt(input.value) < maxQty) {
                input.value = parseInt(input.value) + 1;
                updateCart(itemId, input.value);
            }
        });
    });
    
    selectAllCheckbox.addEventListener('change', function() {
        itemCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        calculateSelectedTotal();
    });
    
    itemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            calculateSelectedTotal();
        });
    });
    
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        const checkedItems = Array.from(itemCheckboxes).filter(cb => cb.checked);
        if (checkedItems.length === 0) {
            e.preventDefault();
            alert('Please select at least one item to checkout.');
        }
    });
    
    calculateSelectedTotal();
});
</script>

<?php include_once '../includes/footer.php'; ?>