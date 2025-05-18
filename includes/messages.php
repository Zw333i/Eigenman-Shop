<?php
if (isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
    $messageType = $_SESSION['message_type']; // success, warning, danger, info
    $alertClass = "alert-{$messageType}";
    
    echo '<div class="container mt-3">';
    echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
    echo $_SESSION['message'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    echo '</div>';
    
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>