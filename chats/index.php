<?php
// chats/index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['userId'])) {
    $_SESSION['error_message'] = 'Please log in to access chats';
    header('Location: ../auth/login.php');
    exit;
}

$userId = $_SESSION['userId'];
$contacts = [];
$messages = [];
$currentChatPartner = null;
$errorMsg = '';
$successMsg = '';

if (isset($_SESSION['success_message'])) {
    $successMsg = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $errorMsg = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_GET['recipient']) && is_numeric($_GET['recipient'])) {
    $recipientId = (int)$_GET['recipient'];
    
    $stmt = $conn->prepare("SELECT userId, username, firstname, lastname, profilePicture, role FROM User WHERE userId = ?");
    $stmt->bind_param("i", $recipientId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $currentChatPartner = $result->fetch_assoc();
    }
    $stmt->close();
}

$contactQuery = "SELECT DISTINCT 
                    u.userId, 
                    u.username, 
                    u.firstname, 
                    u.lastname, 
                    u.profilePicture,
                    u.role,
                    (SELECT MAX(c.timestamp) 
                     FROM Chats c 
                     WHERE (c.senderId = u.userId AND c.receiverId = ?) 
                        OR (c.senderId = ? AND c.receiverId = u.userId)
                    ) as last_message_time,
                    (SELECT COUNT(*) 
                     FROM Chats 
                     WHERE senderId = u.userId 
                     AND receiverId = ? 
                     AND isRead = 0) as unread_count
                FROM User u
                INNER JOIN Chats c 
                ON (u.userId = c.senderId OR u.userId = c.receiverId)
                WHERE (c.senderId = ? OR c.receiverId = ?)
                AND u.userId != ?
                ORDER BY last_message_time DESC";

$stmt = $conn->prepare($contactQuery);
$stmt->bind_param("iiiiii", $userId, $userId, $userId, $userId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $contacts[] = $row;
}
$stmt->close();

if ($currentChatPartner) {
    $messageQuery = "SELECT * FROM Chats 
                    WHERE (senderId = ? AND receiverId = ?) 
                    OR (senderId = ? AND receiverId = ?) 
                    ORDER BY timestamp ASC";
    
    $stmt = $conn->prepare($messageQuery);
    $recipientId = $currentChatPartner['userId'];
    $stmt->bind_param("iiii", $userId, $recipientId, $recipientId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
    
    $updateQuery = "UPDATE Chats SET isRead = 1 WHERE senderId = ? AND receiverId = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ii", $recipientId, $userId);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && $currentChatPartner) {
    $messageContent = trim($_POST['message_content']);
    $recipientId = $currentChatPartner['userId'];
    
    if (!empty($messageContent)) {
        $stmt = $conn->prepare("INSERT INTO Chats (senderId, receiverId, message, timestamp, isRead) VALUES (?, ?, ?, NOW(), 0)");
        $stmt->bind_param("iis", $userId, $recipientId, $messageContent);
        
        if ($stmt->execute()) {
            $newMessageId = $conn->insert_id;
            $stmt->close();
            
            $messages[] = [
                'chatId' => $newMessageId,
                'senderId' => $userId,
                'receiverId' => $recipientId,
                'message' => $messageContent,
                'timestamp' => date('Y-m-d H:i:s'),
                'isRead' => 0
            ];
            
            header("Location: index.php?recipient=" . $recipientId);
            exit;
        } else {
            $errorMsg = "Error sending message: " . $conn->error;
        }
    } else {
        $errorMsg = "Cannot send an empty message";
    }
}

$searchResults = [];
if (isset($_GET['search_term']) && !empty($_GET['search_term'])) {
    $searchTerm = '%' . $_GET['search_term'] . '%';
    
    $searchQuery = "SELECT userId, username, firstname, lastname, profilePicture, role 
                   FROM User 
                   WHERE (username LIKE ? OR firstname LIKE ? OR lastname LIKE ?)
                   AND userId != ? 
                   ORDER BY username";
    
    $stmt = $conn->prepare($searchQuery);
    $stmt->bind_param("sssi", $searchTerm, $searchTerm, $searchTerm, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $searchResults[] = $row;
    }
    $stmt->close();
}

$customHeadContent = '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
';

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row g-4">
        <div class="col-lg-4 col-xl-3">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-gradient text-white d-flex align-items-center">
                    <i class="fas fa-comments me-2"></i>
                    <h5 class="mb-0">Messages</h5>
                </div>
                <div class="card-body p-0">
                    <div class="search-bar p-3 border-bottom">
                        <form action="index.php" method="GET" class="mb-0">
                            <div class="input-group">
                                <input type="text" class="form-control border-end-0 shadow-none" name="search_term" placeholder="Search for users..." value="<?php echo isset($_GET['search_term']) ? htmlspecialchars($_GET['search_term']) : ''; ?>">
                                <button class="btn btn-outline-secondary border-start-0" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <?php if (isset($_GET['search_term']) && !empty($_GET['search_term'])): ?>
                        <div class="p-3 border-bottom">
                            <a href="index.php" class="btn btn-sm btn-outline-secondary mb-2">
                                <i class="fas fa-arrow-left"></i> Back to Chats
                            </a>
                            <h6 class="text-muted px-2 mb-0 mt-2">Results for "<?php echo htmlspecialchars($_GET['search_term']); ?>"</h6>
                        </div>
                        <div class="contact-list">
                            <?php if (empty($searchResults)): ?>
                                <div class="text-center p-4 text-muted">
                                    <i class="fas fa-user-slash fs-3 mb-3"></i>
                                    <p class="mb-0">No users found</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($searchResults as $contact): ?>
                                    <a href="index.php?recipient=<?php echo $contact['userId']; ?>" class="contact-item d-flex align-items-center p-3 border-bottom text-decoration-none">
                                        <div class="profile-image me-3 position-relative">
                                            <?php if (!empty($contact['profilePicture']) && file_exists("../" . $contact['profilePicture'])): ?>
                                                <img src="../<?php echo htmlspecialchars($contact['profilePicture']); ?>" class="rounded-circle shadow-sm" width="50" height="50" style="object-fit: cover;" alt="Profile">
                                            <?php else: ?>
                                                <div class="default-avatar rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 48px; height: 48px;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($contact['role'] === 'merchant'): ?>
                                                <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-primary" style="transform: translate(25%, 25%);">
                                                    <i class="fas fa-store"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="contact-info">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars($contact['firstname'] . ' ' . $contact['lastname']); ?></h6>
                                            </div>
                                            <small class="text-muted">@<?php echo htmlspecialchars($contact['username']); ?></small>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Recent Contacts -->
                        <div class="contact-list">
                            <?php if (empty($contacts)): ?>
                                <div class="text-center p-4 text-muted empty-contact-list">
                                    <i class="fas fa-comments-alt fs-2 mb-3"></i>
                                    <p class="mb-1">No conversations yet</p>
                                    <small>Search for users to start chatting</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($contacts as $contact): ?>
                                    <a href="index.php?recipient=<?php echo $contact['userId']; ?>" 
                                       class="contact-item d-flex align-items-center p-3 border-bottom text-decoration-none
                                       <?php echo (isset($currentChatPartner) && $currentChatPartner['userId'] == $contact['userId']) ? 'active-contact' : ''; ?>">
                                        <div class="profile-image me-3 position-relative">
                                            <?php if (!empty($contact['profilePicture']) && file_exists("../" . $contact['profilePicture'])): ?>
                                                <img src="../<?php echo htmlspecialchars($contact['profilePicture']); ?>" class="rounded-circle shadow-sm" width="50" height="50" style="object-fit: cover;" alt="Profile">
                                            <?php else: ?>
                                                <div class="default-avatar rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 48px; height: 48px;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ((int)$contact['unread_count'] > 0): ?>
                                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                                    <?php echo (int)$contact['unread_count']; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($contact['role'] === 'merchant'): ?>
                                                <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-primary" style="transform: translate(25%, 25%);">
                                                    <i class="fas fa-store"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="contact-info flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars($contact['firstname'] . ' ' . $contact['lastname']); ?></h6>
                                                <small class="text-muted">
                                                    <?php 
                                                    $timestamp = strtotime($contact['last_message_time']);
                                                    $now = time();
                                                    $diff = $now - $timestamp;
                                                    
                                                    if ($diff < 60) {
                                                        echo '<span class="text-success">just now</span>';
                                                    } elseif ($diff < 3600) {
                                                        echo floor($diff / 60) . 'm ago';
                                                    } elseif ($diff < 86400) {
                                                        echo floor($diff / 3600) . 'h ago';
                                                    } else {
                                                        echo date('M j', $timestamp);
                                                    }
                                                    ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">@<?php echo htmlspecialchars($contact['username']); ?></small>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Chat Content -->
        <div class="col-lg-8 col-xl-9">
            <div class="card shadow-sm border-0 rounded-3 chat-container">
                <?php if ($currentChatPartner): ?>
                    <!-- Chat Header -->
                    <div class="card-header chat-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="profile-image me-3 position-relative">
                                    <?php if (!empty($currentChatPartner['profilePicture']) && file_exists("../" . $currentChatPartner['profilePicture'])): ?>
                                        <img src="../<?php echo htmlspecialchars($currentChatPartner['profilePicture']); ?>" class="rounded-circle shadow-sm" width="60" height="60" style="object-fit: cover;" alt="Profile">
                                    <?php else: ?>
                                        <div class="default-avatar rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 52px; height: 52px;">
                                            <i class="fas fa-user fs-4"></i>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($currentChatPartner['role'] === 'merchant'): ?>
                                        <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-primary" style="transform: translate(25%, 25%);">
                                            <i class="fas fa-store"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h5 class="mb-0 fw-semibold"><?php echo htmlspecialchars($currentChatPartner['firstname'] . ' ' . $currentChatPartner['lastname']); ?></h5>
                                    <small class="text-muted">@<?php echo htmlspecialchars($currentChatPartner['username']); ?></small>
                                </div>
                            </div>
                            <?php if ($currentChatPartner['role'] === 'merchant'): ?>
                                <!-- Fetch merchant info to get the store ID -->
                                <?php
                                $stmt = $conn->prepare("SELECT merchantId FROM Merchant WHERE userId = ?");
                                $merchantUserId = $currentChatPartner['userId'];
                                $stmt->bind_param("i", $merchantUserId);
                                $stmt->execute();
                                $merchantResult = $stmt->get_result();
                                if ($merchantRow = $merchantResult->fetch_assoc()) {
                                    $merchantId = $merchantRow['merchantId'];
                                    echo '<a href="../merchant/store.php?id=' . $merchantId . '" class="btn btn-primary btn-sm">
                                        <i class="fas fa-store me-1"></i> Visit Store
                                    </a>';
                                }
                                $stmt->close();
                                ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Chat Messages -->
                    <div class="card-body chat-messages" id="chat-messages">
                        <?php if (empty($messages)): ?>
                            <div class="text-center p-5 text-muted empty-chat animate__animated animate__fadeIn">
                                <i class="fas fa-comments fs-1 mb-4"></i>
                                <p class="mb-2">No messages yet with <?php echo htmlspecialchars($currentChatPartner['firstname']); ?></p>
                                <p class="small">Send a message to start the conversation</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            $prevDate = null;
                            foreach ($messages as $index => $message): 
                                $messageDate = date('Y-m-d', strtotime($message['timestamp']));
                                if ($prevDate !== $messageDate) {
                                    echo '<div class="chat-date-divider">
                                        <span>' . date('F j, Y', strtotime($message['timestamp'])) . '</span>
                                    </div>';
                                    $prevDate = $messageDate;
                                }
                                
                                $animationDelay = min($index * 0.05, 0.5);
                            ?>
                                <div class="message-container <?php echo ($message['senderId'] == $userId) ? 'outgoing' : 'incoming'; ?> animate__animated animate__fadeInUp" style="animation-delay: <?php echo $animationDelay; ?>s;">
                                    <div class="message-bubble">
                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                        <div class="message-info">
                                            <small class="message-time">
                                                <?php echo date('h:i A', strtotime($message['timestamp'])); ?>
                                                <?php if ($message['senderId'] == $userId): ?>
                                                    <?php if ($message['isRead']): ?>
                                                        <i class="fas fa-check-double text-primary"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-check"></i>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
<div class="card-footer bg-white p-3">
    <form method="POST" class="message-form d-flex align-items-stretch gap-2">
        <textarea class="form-control flex-grow-1 message-input shadow-none" 
                 name="message_content" 
                 placeholder="Type something..." 
                 rows="1" required></textarea>
        <input type="hidden" name="send_message" value="1">
 <button class="button send-button">
    <div class="outline"></div>
    <div class="state state--default">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" height="1.2em" width="1.2em">
                            <g style="filter: url(#shadow)">
                                <path fill="currentColor" d="M14.2199 21.63C13.0399 21.63 11.3699 20.8 10.0499 16.83L9.32988 14.67L7.16988 13.95C3.20988 12.63 2.37988 10.96 2.37988 9.78001C2.37988 8.61001 3.20988 6.93001 7.16988 5.60001L15.6599 2.77001C17.7799 2.06001 19.5499 2.27001 20.6399 3.35001C21.7299 4.43001 21.9399 6.21001 21.2299 8.33001L18.3999 16.82C17.0699 20.8 15.3999 21.63 14.2199 21.63ZM7.63988 7.03001C4.85988 7.96001 3.86988 9.06001 3.86988 9.78001C3.86988 10.5 4.85988 11.6 7.63988 12.52L10.1599 13.36C10.3799 13.43 10.5599 13.61 10.6299 13.83L11.4699 16.35C12.3899 19.13 13.4999 20.12 14.2199 20.12C14.9399 20.12 16.0399 19.13 16.9699 16.35L19.7999 7.86001C20.3099 6.32001 20.2199 5.06001 19.5699 4.41001C18.9199 3.76001 17.6599 3.68001 16.1299 4.19001L7.63988 7.03001Z"></path>
                                <path fill="currentColor" d="M10.11 14.4C9.92005 14.4 9.73005 14.33 9.58005 14.18C9.29005 13.89 9.29005 13.41 9.58005 13.12L13.16 9.53C13.45 9.24 13.93 9.24 14.22 9.53C14.51 9.82 14.51 10.3 14.22 10.59L10.64 14.18C10.5 14.33 10.3 14.4 10.11 14.4Z"></path>
                            </g>
                            <defs>
                                <filter id="shadow">
                                    <fedropshadow flood-opacity="0.6" stdDeviation="0.8" dy="1" dx="0"></fedropshadow>
                                </filter>
                            </defs>
                        </svg>
                    </div>
                    <p>
                        <span style="--i:0">S</span>
                        <span style="--i:1">e</span>
                        <span style="--i:2">n</span>
                        <span style="--i:3">d</span>
                        <span style="--i:4"> </span>
                        <span style="--i:5">M</span>
                        <span style="--i:6">e</span>
                        <span style="--i:7">s</span>
                        <span style="--i:8">s</span>
                        <span style="--i:9">a</span>
                        <span style="--i:10">g</span>
                        <span style="--i:11">e</span>
                    </p>
                </div>
                <div class="state state--sent">
                    <div class="icon">
                        <svg stroke="black" stroke-width="0.5px" width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g style="filter: url(#shadow)">
                                <path d="M12 22.75C6.07 22.75 1.25 17.93 1.25 12C1.25 6.07 6.07 1.25 12 1.25C17.93 1.25 22.75 6.07 22.75 12C22.75 17.93 17.93 22.75 12 22.75ZM12 2.75C6.9 2.75 2.75 6.9 2.75 12C2.75 17.1 6.9 21.25 12 21.25C17.1 21.25 21.25 17.1 21.25 12C21.25 6.9 17.1 2.75 12 2.75Z" fill="currentColor"></path>
                                <path d="M10.5795 15.5801C10.3795 15.5801 10.1895 15.5001 10.0495 15.3601L7.21945 12.5301C6.92945 12.2401 6.92945 11.7601 7.21945 11.4701C7.50945 11.1801 7.98945 11.1801 8.27945 11.4701L10.5795 13.7701L15.7195 8.6301C16.0095 8.3401 16.4895 8.3401 16.7795 8.6301C17.0695 8.9201 17.0695 9.4001 16.7795 9.6901L11.1095 15.3601C10.9695 15.5001 10.7795 15.5801 10.5795 15.5801Z" fill="currentColor"></path>
                            </g>
                        </svg>
                    </div>
                    <p>
                        <span style="--i:5">S</span>
                        <span style="--i:6">e</span>
                        <span style="--i:7">n</span>
                        <span style="--i:8">t</span>
                        <span style="--i:9">!</span>
                    </p>
                </div>
            </button>
        </div>
    </form>
</div>
                <?php else: ?>
                    <div class="card-body d-flex flex-column justify-content-center align-items-center select-chat animate__animated animate__fadeIn">
                        <div class="text-center p-5">
                            <div class="chat-empty-icon-container mb-4">
                                <i class="fas fa-comments text-muted"></i>
                            </div>
                            <h4 class="fw-semibold">Select a conversation</h4>
                            <p class="text-muted">Choose a contact from the list or search for someone new</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
<link rel="stylesheet" href="chats.css">
