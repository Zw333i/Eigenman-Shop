<?php
// notifications/index.php
session_start();

if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
include_once '../includes/header.php';

$userId = $_SESSION['userId'];
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';

$notifications = [];
$unreadCount = 0;
try {
    $query = "SELECT n.*, u.username, u.profilePicture, o.itemId 
              FROM Notifications n 
              LEFT JOIN User u ON n.senderId = u.userId 
              LEFT JOIN Orders o ON n.orderId = o.orderId 
              WHERE n.receiverId = ? 
              ORDER BY n.timestamp DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
        // Check if notification is unread (NULL or 0 means unread, 1 means read)
        if (is_null($row['isRead']) || $row['isRead'] == 0) {
            $unreadCount++;
        }
    }
} catch (Exception $e) {
    $error = "Error fetching notifications: " . $e->getMessage();
}

// Function to get proper profile picture URL
function getProfilePictureUrl($profilePicture) {
    if (empty($profilePicture)) {
        return null;
    }
    
    // Clean the path - remove any leading slashes or dots
    $cleanPath = ltrim($profilePicture, './');
    
    // If the path already starts with assets/, use it with ../
    if (strpos($cleanPath, 'assets/') === 0) {
        return '../' . $cleanPath;
    }
    
    // If it doesn't start with assets/, assume it needs the full path
    return '../assets/images/profiles/' . $cleanPath;
}

// Function to get notification icon based on type
function getNotificationIcon($message) {
    $message = strtolower($message);
    if (strpos($message, 'order') !== false) {
        return 'fas fa-shopping-bag';
    } elseif (strpos($message, 'message') !== false) {
        return 'fas fa-envelope';
    } elseif (strpos($message, 'payment') !== false) {
        return 'fas fa-credit-card';
    } elseif (strpos($message, 'delivery') !== false || strpos($message, 'shipped') !== false) {
        return 'fas fa-truck';
    } else {
        return 'fas fa-bell';
    }
}

// Function to get the correct order URL based on user role
function getOrderUrl($orderId, $userRole) {
    if ($userRole == 'merchant') {
        return "../merchant/orders.php?highlight=" . $orderId;
    } else {
        return "../order/details.php?id=" . $orderId;
    }
}
?>

<style>
.notification-card {
    border: none;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border-radius: 12px;
    overflow: hidden;
}

.notification-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 1.5rem;
}

.notification-item {
    transition: all 0.2s ease;
    border-left: 4px solid transparent;
    position: relative;
    cursor: pointer;
}

.notification-item:hover {
    background-color: #f8fafc;
    border-left-color: #667eea;
    transform: translateX(2px);
}

.notification-item.unread {
    background-color: #f0f9ff;
    border-left-color: #3b82f6;
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    top: 50%;
    right: 1rem;
    transform: translateY(-50%);
    width: 8px;
    height: 8px;
    background-color: #3b82f6;
    border-radius: 50%;
}

.profile-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e5e7eb;
}

.avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.notification-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    margin-left: auto;
}

.icon-order { background-color: #dbeafe; color: #1d4ed8; }
.icon-message { background-color: #dcfce7; color: #166534; }
.icon-payment { background-color: #fef3c7; color: #92400e; }
.icon-delivery { background-color: #f3e8ff; color: #7c3aed; }
.icon-default { background-color: #f1f5f9; color: #475569; }

.btn-modern {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
}

.btn-primary-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-outline-modern {
    border: 1.5px solid #e5e7eb;
    color: #374151;
    background: white;
}

.btn-outline-modern:hover {
    border-color: #667eea;
    color: #667eea;
    background: #f8fafc;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state-icon {
    font-size: 4rem;
    color: #d1d5db;
    margin-bottom: 1.5rem;
}

.sidebar-modern {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.sidebar-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.25rem;
    font-weight: 600;
}

.sidebar-item {
    border: none;
    color: #374151;
    padding: 0.875rem 1.25rem;
    transition: all 0.2s ease;
    text-decoration: none;
    display: block;
}

.sidebar-item:hover {
    background-color: #f8fafc;
    color: #667eea;
    padding-left: 1.5rem;
}

.sidebar-item.active {
    background-color: #f0f9ff;
    color: #1d4ed8;
    border-right: 3px solid #3b82f6;
    font-weight: 500;
}

.badge-count {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-weight: 600;
    min-width: 1.5rem;
    text-align: center;
}

@media (max-width: 768px) {
    .notification-item {
        padding: 1rem !important;
    }
    
    .profile-avatar, .avatar-placeholder {
        width: 40px;
        height: 40px;
    }
    
    .notification-icon {
        width: 32px;
        height: 32px;
    }
}
</style>

<div class="container-fluid py-4">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php if($userRole == 'merchant'): ?>
                <?php include_once '../merchant/sidebar.php'; ?>
            <?php else: ?>
                <div class="sidebar-modern">
                    <div class="sidebar-header">
                        <h5 class="mb-0">
                            <i class="fas fa-compass me-2"></i>Navigation
                        </h5>
                    </div>
                    <div>
                        <a href="../product/index.php" class="sidebar-item">
                            <i class="fas fa-store me-3"></i>Browse Products
                        </a>
                        <a href="../cart/view.php" class="sidebar-item">
                            <i class="fas fa-shopping-cart me-3"></i>My Cart
                        </a>
                        <a href="../order/history.php" class="sidebar-item">
                            <i class="fas fa-history me-3"></i>Order History
                        </a>
                        <a href="../notifications/index.php" class="sidebar-item active">
                            <i class="fas fa-bell me-3"></i>Notifications
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge-count ms-auto"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="../chats/index.php" class="sidebar-item">
                            <i class="fas fa-comments me-3"></i>Messages
                        </a>
                        <a href="../auth/profile.php" class="sidebar-item">
                            <i class="fas fa-user me-3"></i>My Profile
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-9">
            <div class="notification-card">
                <div class="notification-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0 text-white">
                            <i class="fas fa-bell me-2"></i>Notifications
                        </h4>
                        <?php if ($unreadCount > 0): ?>
                            <small class="text-white-50"><?php echo $unreadCount; ?> unread notification<?php echo $unreadCount > 1 ? 's' : ''; ?></small>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($notifications)): ?>
                        <button id="markAllRead" class="btn btn-light btn-modern">
                            <i class="fas fa-check-double me-2"></i>Mark All Read
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-bell-slash"></i>
                            </div>
                            <h5 class="text-muted mb-3">No notifications yet</h5>
                            <p class="text-muted mb-4">You'll see updates about your orders, messages, and other activities here.</p>
                            <?php if($userRole == 'merchant'): ?>
                                <a href="../merchant/dashboard.php" class="btn btn-primary-modern btn-modern">
                                    <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                                </a>
                            <?php else: ?>
                                <a href="../product/index.php" class="btn btn-primary-modern btn-modern">
                                    <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="notification-list">
                            <?php foreach($notifications as $index => $notif): ?>
                                <div class="notification-item p-4 <?php echo (is_null($notif['isRead']) || $notif['isRead'] == 0) ? 'unread' : ''; ?> <?php echo $index < count($notifications) - 1 ? 'border-bottom' : ''; ?>" 
                                     data-notif-id="<?php echo $notif['notifId']; ?>">
                                    <div class="d-flex align-items-start">
                                        <div class="me-3">
                                            <?php 
                                            $profilePicUrl = getProfilePictureUrl($notif['profilePicture']);
                                            if ($profilePicUrl): 
                                            ?>
                                                <img src="<?php echo htmlspecialchars($profilePicUrl); ?>" 
                                                     class="profile-avatar" alt="Profile Picture"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="avatar-placeholder" style="display: none;">
                                                    <?php echo strtoupper(substr($notif['username'] ?: 'S', 0, 1)); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="avatar-placeholder">
                                                    <?php echo strtoupper(substr($notif['username'] ?: 'S', 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="flex-grow-1 me-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="mb-0 fw-bold text-dark">
                                                    <?php echo !empty($notif['username']) ? htmlspecialchars($notif['username']) : 'System'; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo formatTimestamp($notif['timestamp']); ?>
                                                </small>
                                            </div>
                                            
                                            <p class="mb-3 text-secondary" style="line-height: 1.5;">
                                                <?php echo htmlspecialchars($notif['message']); ?>
                                            </p>
                                            
                                            <?php if ($notif['orderId']): ?>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <a href="<?php echo getOrderUrl($notif['orderId'], $userRole); ?>" 
                                                       class="btn btn-primary-modern btn-modern btn-sm">
                                                        <i class="fas fa-eye me-1"></i>
                                                        <?php echo ($userRole == 'merchant') ? 'Manage Order' : 'View Order'; ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="notification-icon icon-<?php 
                                            $message = strtolower($notif['message']);
                                            if (strpos($message, 'order') !== false) echo 'order';
                                            elseif (strpos($message, 'message') !== false) echo 'message';
                                            elseif (strpos($message, 'payment') !== false) echo 'payment';
                                            elseif (strpos($message, 'delivery') !== false || strpos($message, 'shipped') !== false) echo 'delivery';
                                            else echo 'default';
                                        ?>">
                                            <i class="<?php echo getNotificationIcon($notif['message']); ?>"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Mark all notifications as read
document.getElementById('markAllRead')?.addEventListener('click', function() {
    const button = this;
    const originalText = button.innerHTML;
    
    // Show loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Marking...';
    button.disabled = true;
    
    fetch('../api/mark_notifications_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            userId: <?php echo $userId; ?>
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove unread styling from all notifications
            const notifications = document.querySelectorAll('.notification-item.unread');
            notifications.forEach(notif => {
                notif.classList.remove('unread');
            });
            
            // Update the notification counter in the sidebar
            const notifCounter = document.querySelector('.badge-count');
            if (notifCounter) {
                notifCounter.style.display = 'none';
            }
            
            // Hide the button after successful marking
            button.style.display = 'none';
            
            // Show success toast (if you have a toast system)
            if (typeof showToast === 'function') {
                showToast('All notifications marked as read', 'success');
            }
        } else {
            // Reset button on error
            button.innerHTML = originalText;
            button.disabled = false;
            
            if (typeof showToast === 'function') {
                showToast('Failed to mark notifications as read', 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Reset button on error
        button.innerHTML = originalText;
        button.disabled = false;
    });
});

// Auto-refresh notifications every 30 seconds
setInterval(() => {
    // Only refresh if the page is visible to avoid unnecessary requests
    if (!document.hidden) {
        location.reload();
    }
}, 30000);

// Handle notification item clicks to mark individual items as read
document.querySelectorAll('.notification-item.unread').forEach(item => {
    item.addEventListener('click', function() {
        const notifId = this.dataset.notifId;
        
        // Mark as read visually immediately
        this.classList.remove('unread');
        
        // Send request to mark as read
        fetch('../api/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                notifId: notifId
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update notification counter
                const notifCounter = document.querySelector('.badge-count');
                if (notifCounter) {
                    const currentCount = parseInt(notifCounter.textContent);
                    const newCount = currentCount - 1;
                    if (newCount <= 0) {
                        notifCounter.style.display = 'none';
                    } else {
                        notifCounter.textContent = newCount;
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
            // Re-add unread class if there was an error
            this.classList.add('unread');
        });
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>