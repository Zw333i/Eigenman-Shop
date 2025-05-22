<?php
// auth/forgot-password.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// If user is already logged in, redirect them
if (isset($_SESSION['userId'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: ../admin/dashboard.php");
            break;
        case 'merchant':
            header("Location: ../merchant/dashboard.php");
            break;
        case 'customer':
            header("Location: ../index.php");
            break;
        default:
            header("Location: ../index.php");
            break;
    }
    exit();
}

$username = "";
$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($errors)) {
        // Check if user exists with this username
        $stmt = $conn->prepare("SELECT userId, username, firstname, securityQuestion FROM User WHERE username = ?");
        
        if ($stmt === false) {
            $errors[] = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Generate a unique reset token
                $token = bin2hex(random_bytes(32));
                $expires = time() + (60 * 60); // Token expires in 1 hour
                
                // Store reset token in session instead of database
                if (!isset($_SESSION['reset_tokens'])) {
                    $_SESSION['reset_tokens'] = [];
                }
                
                // Clean up expired tokens
                foreach ($_SESSION['reset_tokens'] as $key => $tokenData) {
                    if ($tokenData['expires'] < time()) {
                        unset($_SESSION['reset_tokens'][$key]);
                    }
                }
                
                // Store the new token with security question
                $_SESSION['reset_tokens'][$token] = [
                    'userId' => $user['userId'],
                    'username' => $user['username'],
                    'firstname' => $user['firstname'],
                    'securityQuestion' => $user['securityQuestion'],
                    'expires' => $expires,
                    'created' => time()
                ];
                
                // Create password reset link
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
                
                // Log the password reset request activity
                $activityMsg = "Password reset requested on " . date("m/d/Y H:i:s");
                $activityStmt = $conn->prepare("UPDATE User SET userActivities = CONCAT(IFNULL(userActivities, ''), '\n', ?) WHERE userId = ?");
                
                if ($activityStmt === false) {
                    error_log("Failed to prepare activity update statement: " . $conn->error);
                } else {
                    $activityStmt->bind_param("si", $activityMsg, $user['userId']);
                    $activityStmt->execute();
                    $activityStmt->close();
                }
                
                // Success message with reset link
                $success = "Password reset instructions have been generated. For testing purposes, use this link: <a href='$resetLink' class='alert-link'>Reset Password</a><br><small class='text-muted'>You will need to answer your security question to complete the reset.</small>";
                
                // TODO: In a real application, you would send this via email or SMS
                // Example:
                // $subject = "Password Reset Request";
                // $message = "Hi " . $user['firstname'] . ", click the following link to reset your password: " . $resetLink;
                // mail($userEmail, $subject, $message);
                
            } else {
                // User doesn't exist - inform the user explicitly
                $errors[] = "No account found with the username '" . htmlspecialchars($username) . "'. Please check your username and try again.";
            }
            
            $stmt->close();
        }
    }
}

$pageTitle = "Forgot Password";
include_once '../includes/header.php';
?>

<div class="container py-5 main-container">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="auth-form">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-lock fa-3x text-primary"></i>
                    </div>
                    <h2 class="form-title">Forgot Password</h2>
                    <p class="text-muted">Enter your username to reset your password</p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-check-circle me-2 mt-1"></i>
                            <div>
                                <?php echo $success; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($success)): ?>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" novalidate>
                    <div class="mb-4">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-user text-primary"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" placeholder="Enter your username" required>
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            You will need to answer your security question to reset your password
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mb-4">
                        <button type="submit" class="btn btn-primary py-2">
                            <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                        </button>
                    </div>
                </form>
                <?php endif; ?>
                
                <div class="text-center">
                    <p class="mb-2">Remember your password? <a href="login.php" class="text-primary">Sign In</a></p>
                    <p class="mb-0">Don't have an account? <a href="register.php" class="text-primary">Create Account</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">