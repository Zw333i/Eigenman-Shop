<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Include database and header
require_once '../config/database.php';
require_once '../includes/functions.php';
include_once '../includes/header.php';

// Get user ID
$userId = $_SESSION['userId'];
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';

// Fetch notifications for the user
$notifications = [];
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
    }
    
    // Mark notifications as read (in a real application, you might want this to be more selective)
    $updateQuery = "UPDATE Notifications SET isRead = 1 WHERE receiverId = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("i", $userId);
    $updateStmt->execute();
    
} catch (Exception $e) {
    $error = "Error fetching notifications: " . $e->getMessage();
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-3">
            <?php if($userRole == 'merchant'): ?>
                <?php include_once '../merchant/sidebar.php'; ?>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Navigation</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="../product/index.php" class="list-group-item list-group-item-action">Browse Products</a>
                        <a href="../cart/view.php" class="list-group-item list-group-item-action">My Cart</a>
                        <a href="../order/history.php" class="list-group-item list-group-item-action">Order History</a>
                        <a href="../notifications/index.php" class="list-group-item list-group-item-action active">Notifications</a>
                        <a href="../chats/index.php" class="list-group-item list-group-item-action">Messages</a>
                        <a href="../auth/profile.php" class="list-group-item list-group-item-action">My Profile</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-9">
            <div class="card shadow">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Notifications</h4>
                    <button id="markAllRead" class="btn btn-sm btn-light">Mark All as Read</button>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center p-5">
                            <i class="fas fa-bell fa-3x text-muted mb-3"></i>
                            <h5>No notifications yet</h5>
                            <p class="text-muted">You'll see updates about your orders, messages, and other activities here.</p>
                        </div>
                    <?php else: ?>
                        <div class="notification-list">
                            <?php foreach($notifications as $notif): ?>
                                <div class="notification-item p-3 border-bottom" 
                                     data-notif-id="<?php echo $notif['notifId']; ?>">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <?php if ($notif['profilePicture']): ?>
                                                <img src="<?php echo htmlspecialchars($notif['profilePicture']); ?>" 
                                                     class="rounded-circle" width="50" height="50" alt="Profile">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" 
                                                     style="width: 50px; height: 50px;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h6 class="mb-0 fw-bold">
                                                    <?php echo !empty($notif['username']) ? htmlspecialchars($notif['username']) : 'System'; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo formatTimestamp($notif['timestamp']); ?>
                                                </small>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                            
                                            <?php if ($notif['orderId']): ?>
                                                <a href="../order/details.php?id=<?php echo $notif['orderId']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">View Order</a>
                                            <?php endif; ?>
                                            
                                            <?php if ($notif['senderId'] && $notif['senderId'] != $userId): ?>
                                                <a href="../chats/index.php?user=<?php echo $notif['senderId']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary ms-2">Send Message</a>
                                            <?php endif; ?>
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
// Function to format notification timestamps (if not defined in functions.php)
function formatTimeAgo(timestamp) {
    const now = new Date();
    const past = new Date(timestamp);
    const diffMs = now - past;
    
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    
    if (diffSecs < 60) {
        return diffSecs + " seconds ago";
    } else if (diffMins < 60) {
        return diffMins + " minutes ago";
    } else if (diffHours < 24) {
        return diffHours + " hours ago";
    } else if (diffDays < 7) {
        return diffDays + " days ago";
    } else {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return past.toLocaleDateString(undefined, options);
    }
}

// Mark all notifications as read
document.getElementById('markAllRead').addEventListener('click', function() {
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
            const notifications = document.querySelectorAll('.notification-item');
            notifications.forEach(notif => {
                notif.classList.remove('unread');
            });
            
            // Update the notification counter in the navbar (assuming there's one)
            const notifCounter = document.querySelector('.notification-counter');
            if (notifCounter) {
                notifCounter.textContent = '0';
                notifCounter.style.display = 'none';
            }
        }
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>