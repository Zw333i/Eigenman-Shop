<?php
// includes/navbar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('mysqli_connect') && file_exists($_SERVER['DOCUMENT_ROOT'] . '/e-commerce/config/database.php')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/config/database.php';
}

$profilePicture = null;
if (isset($_SESSION['userId']) && isset($conn)) {
    $userId = $_SESSION['userId'];
    $userQuery = "SELECT profilePicture FROM User WHERE userId = $userId";
    $userResult = $conn->query($userQuery);
    if ($userResult && $userResult->num_rows > 0) {
        $userData = $userResult->fetch_assoc();
        $profilePicture = $userData['profilePicture'];
    }
}

$cartCount = 0;
if (isset($_SESSION['userId']) && isset($conn) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'merchant')) {
    $checkTableQuery = "SHOW TABLES LIKE 'UserCart'"; 
    $tableExists = $conn->query($checkTableQuery);
    
    if ($tableExists && $tableExists->num_rows > 0) {
        $userId = $_SESSION['userId'];
        $cartQuery = "SELECT COUNT(*) as count FROM UserCart WHERE userId = $userId";
        $cartResult = $conn->query($cartQuery);
        if ($cartResult && $cartResult->num_rows > 0) {
            $cartData = $cartResult->fetch_assoc();
            $cartCount = $cartData['count'];
        }
    }
}

$unreadMessagesCount = 0;
if (isset($_SESSION['userId']) && isset($conn) && isset($_SESSION['role']) && $_SESSION['role'] === 'merchant') {
    $checkChatTableQuery = "SHOW TABLES LIKE 'Chats'";
    $chatTableExists = $conn->query($checkChatTableQuery);
    
    if ($chatTableExists && $chatTableExists->num_rows > 0) {
        $userId = $_SESSION['userId'];
        $unreadQuery = "SELECT COUNT(*) as count FROM Chats WHERE receiverId = $userId AND isRead = 0";
        $unreadResult = $conn->query($unreadQuery);
        if ($unreadResult && $unreadResult->num_rows > 0) {
            $unreadData = $unreadResult->fetch_assoc();
            $unreadMessagesCount = $unreadData['count'];
        }
    }
}

$notificationsCount = 0;
if (isset($_SESSION['userId']) && isset($conn)) {
    $checkNotifTableQuery = "SHOW TABLES LIKE 'Notifications'";
    $notifTableExists = $conn->query($checkNotifTableQuery);
    
    if ($notifTableExists && $notifTableExists->num_rows > 0) {
        $userId = $_SESSION['userId'];
        // Only count unread notifications (isRead = 0)
        $notifQuery = "SELECT COUNT(*) as count FROM Notifications WHERE receiverId = $userId AND isRead = 0";
        $notifResult = $conn->query($notifQuery);
        if ($notifResult && $notifResult->num_rows > 0) {
            $notifData = $notifResult->fetch_assoc();
            $notificationsCount = $notifData['count'];
        }
    }
}
// Store in session for use across pages
$_SESSION['unreadNotifications'] = $notificationsCount;

$basePath = '/e-commerce/'; 
?>

<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <div class="container">
        <a class="navbar-brand" href="<?php echo $basePath; ?>index.php">
            <img src="<?php echo $basePath; ?>assets/images/eigenman_logo.png" alt="Eigenman Logo">
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $basePath; ?>index.php">
                        <span class="nav-icon"><i class="fas fa-home"></i></span>
                        <span class="nav-text">Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $basePath; ?>product/index.php">
                        <span class="nav-icon"><i class="fas fa-store"></i></span>
                        <span class="nav-text">Products</span>
                    </a>
                </li>
                <?php if (isset($_SESSION['userId']) && $_SESSION['role'] === 'merchant'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $basePath; ?>merchant/dashboard.php">
                        <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['userId']) && $_SESSION['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $basePath; ?>admin/dashboard.php">
                        <span class="nav-icon"><i class="fas fa-tools"></i></span>
                        <span class="nav-text">Admin Panel</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <?php if (isset($_SESSION['userId'])) : ?>
                <!-- Logged in user menu -->
                <div class="d-flex align-items-center user-menu-container">
                    <!-- Notifications icon for all users -->
                    <a href="<?php echo $basePath; ?>notifications/index.php" class="notification-icon position-relative me-3" aria-label="Notifications">
                        <i class="fas fa-bell text-primary fs-5"></i>
                        <?php if ($notificationsCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                            <?php echo $notificationsCount; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    
                    <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'merchant'): ?>
                    <!-- Only show cart icon for non-merchant users -->
                    <a href="<?php echo $basePath; ?>cart/view.php" class="cart-icon position-relative me-3" aria-label="Shopping Cart">
                        <i class="fas fa-shopping-cart text-primary fs-5"></i>
                        <?php if ($cartCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-badge">
                            <?php echo $cartCount; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <?php else: ?>
                    <!-- Show chat icon for merchant users -->
                    <a href="<?php echo $basePath; ?>chats/index.php" class="chat-icon position-relative me-3" aria-label="Messages">
                        <i class="fas fa-comments text-primary fs-5"></i>
                        <?php if ($unreadMessagesCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger chat-badge">
                            <?php echo $unreadMessagesCount; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    
                    <div class="dropdown user-dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-info-container d-flex align-items-center">
                                <!-- Profile picture placement next to username -->
                                <div class="profile-circle me-2">
                                    <?php if (!empty($profilePicture)): ?>
                                    <img src="<?php echo $basePath . $profilePicture; ?>" alt="Profile" class="rounded-circle profile-img">
                                    <?php else: ?>
                                    <div class="profile-initial rounded-circle bg-primary text-white">
                                        <?php echo substr($_SESSION['firstname'] ?? 'U', 0, 1); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <span class="username-text"><?php echo htmlspecialchars($_SESSION['firstname'] ?? $_SESSION['username']); ?></span>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end animate-dropdown" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>auth/profile.php">
                                <i class="fas fa-user-circle me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>notifications/index.php">
                                <i class="fas fa-bell me-2"></i>Notifications
                                <?php if ($notificationsCount > 0): ?>
                                <span class="badge rounded-pill bg-danger ms-2"><?php echo $notificationsCount; ?></span>
                                <?php endif; ?>
                            </a></li>
                            <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'merchant'): ?>
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>order/history.php">
                                <i class="fas fa-shopping-bag me-2"></i>Orders
                            </a></li>
                            <?php else: ?>
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>merchant/orders.php">
                                <i class="fas fa-clipboard-list me-2"></i>Manage Orders
                            </a></li>
                            <?php endif; ?>
                            <!-- Common chat option for all users -->
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>chats/index.php">
                                <i class="fas fa-comments me-2"></i>Messages
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'merchant' && $unreadMessagesCount > 0): ?>
                                <span class="badge rounded-pill bg-danger ms-2"><?php echo $unreadMessagesCount; ?></span>
                                <?php endif; ?>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </div>
                </div>
            <?php else : ?>
                <div class="d-flex auth-buttons">
                    <a href="<?php echo $basePath; ?>auth/login.php" class="btn btn-outline-primary me-2 sign-in-btn">
                        <i class="fas fa-sign-in-alt me-1"></i> Sign In
                    </a>
                    <a href="<?php echo $basePath; ?>auth/register.php" class="btn btn-primary register-btn">
                        <i class="fas fa-user-plus me-1"></i> Register
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<style>
.navbar {
    background-color: #ffffff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    padding: 14px 0;
    transition: all 0.3s ease;
    z-index: 1030; /* Ensure the navbar is above other elements */
}

.navbar.scrolled {
    padding: 8px 0;
}

.navbar-brand img {
    height: 40px;
    transition: all 0.3s ease;
}

.navbar-brand img:hover {
    transform: scale(1.05);
}

.nav-item {
    margin: 0 5px;
    position: relative;
}

.nav-link {
    display: flex;
    align-items: center;
    color: #555;
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.nav-link:hover {
    color: var(--primary-blue);
    background-color: rgba(0,123,255,0.08);
}

.nav-icon {
    margin-right: 6px;
    font-size: 14px;
}

.user-menu-container {
    position: relative;
    z-index: 1031; 
}

.user-dropdown {
    position: relative;
    z-index: 1032; 
}

.user-info-container {
    display: flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 24px;
    transition: all 0.25s ease;
    border: 1px solid transparent;
    cursor: pointer; 
}

.user-info-container:hover {
    background-color: rgba(0,123,255,0.08);
    border-color: rgba(0,123,255,0.2);
}

.profile-circle {
    width: 38px;
    height: 38px;
    overflow: hidden;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 3px 8px rgba(0,0,0,0.12);
    border: 2px solid var(--primary-blue);
    transition: all 0.3s ease;
}

.profile-circle:hover {
    transform: scale(1.08);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
}

.profile-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: all 0.3s ease;
}

.username-text {
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
}

.profile-initial {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
    transition: all 0.3s ease;
}

/* Improved dropdown menu styles to ensure proper visibility */
.dropdown-menu {
    display: none;
    position: absolute;
    z-index: 1033; /* Higher than dropdown container */
}

.dropdown-toggle::after {
    display: inline-block;
}

/* Force dropdown menu to be visible when open */
.dropdown.show .dropdown-menu {
    display: block !important;
}

.dropdown-item {
    padding: 10px 20px;
    transition: all 0.2s ease;
    font-size: 14px;
    border-radius: 4px;
    margin: 0 5px;
}

.dropdown-item i {
    width: 20px;
    text-align: center;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background-color: var(--light-blue);
    color: var(--primary-blue);
    transform: translateX(3px);
}

.dropdown-item:hover i {
    transform: scale(1.2);
}

.dropdown-divider {
    margin: 8px 0;
}

/* Animated dropdown menu */
.animate-dropdown {
    border: none;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    border-radius: 10px;
    padding: 8px 5px;
    animation: dropdown-fade 0.25s ease-out;
    transform-origin: top center;
}

@keyframes dropdown-fade {
    from {
        opacity: 0;
        transform: translateY(-10px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Cart, Chat, and Notification icon animations */
.cart-icon, .chat-icon, .notification-icon {
    position: relative;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 1031; /* Same as user-menu-container */
}

.cart-icon:hover, .chat-icon:hover, .notification-icon:hover {
    transform: scale(1.15);
}

.cart-badge, .chat-badge, .notification-badge {
    animation: badge-pulse 2s infinite;
    font-size: 0.65rem;
    font-weight: 700;
    animation-delay: 1s;
}

@keyframes badge-pulse {
    0% {
        transform: scale(1);
    }
    15% {
        transform: scale(1.25);
    }
    30% {
        transform: scale(1);
    }
}

/* Chat icon specific pulse animation for new messages */
.chat-badge {
    animation: chat-badge-pulse 2s infinite;
}

@keyframes chat-badge-pulse {
    0% {
        transform: scale(1);
    }
    15% {
        transform: scale(1.25);
    }
    30% {
        transform: scale(1);
    }
    45% {
        transform: scale(1.25);
    }
    60% {
        transform: scale(1);
    }
}

/* Notification badge with gentle bell shake animation */
.notification-badge {
    animation: notification-bell-shake 3s infinite;
}

@keyframes notification-bell-shake {
    0%, 50%, 100% {
        transform: scale(1) rotate(0deg);
    }
    10% {
        transform: scale(1.2) rotate(-15deg);
    }
    20% {
        transform: scale(1.1) rotate(10deg);
    }
    30% {
        transform: scale(1.15) rotate(-10deg);
    }
    40% {
        transform: scale(1.05) rotate(5deg);
    }
}

/* Notification icon hover effect with bell shake */
.notification-icon:hover i {
    animation: bell-ring 0.5s ease-in-out;
}

@keyframes bell-ring {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(-10deg); }
    75% { transform: rotate(10deg); }
}

/* Sign in and Register buttons animation */
.auth-buttons .btn {
    transition: all 0.3s ease;
    font-weight: 500;
    border-radius: 6px;
}

.sign-in-btn:hover {
    background-color: rgba(0,123,255,0.1);
    transform: translateY(-2px);
}

.register-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
}

/* Add margin-top to body to prevent navbar overlap */
body {
    padding-top: 80px;
    transition: padding-top 0.3s ease;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .navbar-collapse {
        background-color: white;
        border-radius: 10px;
        padding: 10px;
        margin-top: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        z-index: 1040; /* Ensure mobile menu is above other elements */
    }
    
    .user-info-container {
        margin-top: 10px;
    }
    
    .auth-buttons {
        margin-top: 10px;
        width: 100%;
        justify-content: center;
    }

    /* Make sure dropdown menu is properly displayed on mobile */
    .dropdown-menu {
        position: static !important;
        float: none;
        width: auto;
        margin-top: 0.5rem;
        box-shadow: none;
        border: 1px solid rgba(0,0,0,0.15);
    }
}

@media (min-width: 992px) {
    .dropdown-menu {
        position: absolute !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dropdownToggle = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggle.forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var parent = this.closest('.dropdown');
            if (!parent) return;
            
            var menu = parent.querySelector('.dropdown-menu');
            var isOpen = parent.classList.contains('show');
            
            document.querySelectorAll('.dropdown.show').forEach(function(dropdown) {
                if (dropdown !== parent) {
                    dropdown.classList.remove('show');
                    dropdown.querySelector('.dropdown-menu').classList.remove('show');
                }
            });
            
            if (isOpen) {
                parent.classList.remove('show');
                menu.classList.remove('show');
            } else {
                parent.classList.add('show');
                menu.classList.add('show');
            }
        });
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown.show').forEach(function(dropdown) {
                dropdown.classList.remove('show');
                dropdown.querySelector('.dropdown-menu').classList.remove('show');
            });
        }
    });
});
</script>