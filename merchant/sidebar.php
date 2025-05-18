<?php
// merchant/sidebar.php

$merchantData = [];
$merchantDataQuery = "SELECT m.* FROM Merchant m WHERE m.userId = {$_SESSION['userId']}";
$merchantDataResult = $conn->query($merchantDataQuery);
if ($merchantDataResult && $merchantDataResult->num_rows > 0) {
    $merchantData = $merchantDataResult->fetch_assoc();
}

$pendingOrders = 0;
if (isset($merchantData['merchantId'])) {
    $pendingOrdersQuery = "SELECT COUNT(*) as pending FROM Orders 
                          WHERE merchantId = {$merchantData['merchantId']} 
                          AND (toShip = TRUE OR toReceive = TRUE)";
    $pendingResult = $conn->query($pendingOrdersQuery);
    if ($pendingResult && $pendingResult->num_rows > 0) {
        $pendingData = $pendingResult->fetch_assoc();
        $pendingOrders = $pendingData['pending'];
    }
}
?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="merchant-profile p-3 text-center border-bottom">
            <?php
            $profilePicture = null;
            $profileQuery = "SELECT profilePicture FROM User WHERE userId = {$_SESSION['userId']}";
            $profileResult = $conn->query($profileQuery);
            if ($profileResult && $profileResult->num_rows > 0) {
                $profileData = $profileResult->fetch_assoc();
                $profilePicture = $profileData['profilePicture'];
            }
            ?>
            <div class="d-flex justify-content-center mb-3">
                <div class="profile-img-container">
                    <?php if (!empty($profilePicture)): ?>
                        <img src="/e-commerce/<?php echo $profilePicture; ?>" alt="Profile" class="rounded-circle merchant-profile-img">
                    <?php else: ?>
                        <div class="merchant-profile-initial rounded-circle bg-primary text-white">
                            <?php echo substr($_SESSION['firstname'] ?? 'M', 0, 1); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <h5 class="mb-1"><?php echo htmlspecialchars($merchantData['storeName'] ?? "Your Store"); ?></h5>
            <p class="text-muted small mb-2">
                <?php 
                $fullname = $_SESSION['firstname'] ?? '';
                if (isset($_SESSION['lastname'])) {
                    $fullname .= ' ' . $_SESSION['lastname'];
                }
                echo htmlspecialchars($fullname); 
                ?>
            </p>
            <div class="d-grid gap-2">
                <a href="/e-commerce/auth/profile.php?tab=store" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-store-alt me-1"></i> Edit Store
                </a>
            </div>
        </div>
        
        <ul class="nav flex-column py-2">
            <?php
            $currentPage = basename($_SERVER['PHP_SELF']);
            
            $navItems = [
                'dashboard.php' => ['icon' => 'fas fa-chart-line', 'label' => 'Dashboard'],
                'products.php' => ['icon' => 'fas fa-box', 'label' => 'Products'],
                'orders.php' => ['icon' => 'fas fa-shopping-bag', 'label' => 'Orders'],
            ];
            
            foreach ($navItems as $page => $item):
                $isActive = ($currentPage === $page);
                $activeClass = $isActive ? 'active' : '';
            ?>
            <li class="nav-item">
                <a class="nav-link merchant-nav-link <?php echo $activeClass; ?>" href="/e-commerce/merchant/<?php echo $page; ?>">
                    <i class="<?php echo $item['icon']; ?> me-3"></i>
                    <?php echo $item['label']; ?>
                    <?php if ($page === 'orders.php' && $pendingOrders > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-auto"><?php echo $pendingOrders; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
            
            <li class="nav-item mt-3">
                <a class="nav-link merchant-nav-link" href="/e-commerce/index.php">
                    <i class="fas fa-home me-3"></i>
                    Back to Home
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
.merchant-profile-img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border: 3px solid var(--primary-blue);
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.merchant-profile-initial {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    border: 3px solid var(--primary-blue);
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.merchant-nav-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.25rem;
    color: #495057;
    border-radius: 0.25rem;
    margin: 0.1rem 0.5rem;
    transition: all 0.2s ease;
}

.merchant-nav-link:hover {
    background-color: rgba(0,123,255,0.08);
    color: var(--primary-blue);
}

.merchant-nav-link.active {
    background-color: var(--primary-blue);
    color: white;
    box-shadow: 0 2px 5px rgba(0,123,255,0.2);
}

.merchant-nav-link i {
    width: 20px;
    text-align: center;
}
</style>