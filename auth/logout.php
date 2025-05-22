<?php
//auth/logout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = "You have been successfully logged out.";
$message_type = "success";

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
setcookie("user_id", "", time() - 3600, "/");
session_destroy();

session_start();

$_SESSION['message'] = $message;
$_SESSION['message_type'] = $message_type;

header("Location: /e-commerce/index.php");
exit();
?>