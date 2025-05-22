<?php
// auth/reset-password.php

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

$token = $_GET['token'] ?? '';
$errors = [];
$success = "";
$userInfo = null;
$securityAnswer = "";
$newPassword = "";
$confirmPassword = "";

// Verify token
if (empty($token)) {
    $errors[] = "Invalid reset token";
} else {
    // Check if token exists and is not expired
    if (isset($_SESSION['reset_tokens'][$token])) {
        $tokenData = $_SESSION['reset_tokens'][$token];
        
        if ($tokenData['expires'] < time()) {
            $errors[] = "Reset token has expired. Please request a new password reset.";
            unset($_SESSION['reset_tokens'][$token]);
        } else {
            $userInfo = $tokenData;
        }
    } else {
        $errors[] = "Invalid or expired reset token";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userInfo) {
    $securityAnswer = trim($_POST['securityAnswer'] ?? '');
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    // Validate security answer
    if (empty($securityAnswer)) {
        $errors[] = "Security answer is required";
    } else {
        // Get the stored security answer from database
        $stmt = $conn->prepare("SELECT securityAnswer FROM User WHERE userId = ?");
        $stmt->bind_param("i", $userInfo['userId']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify security answer (case insensitive)
            if (!password_verify(strtolower(trim($securityAnswer)), $user['securityAnswer'])) {
                $errors[] = "Incorrect security answer";
            }
        } else {
            $errors[] = "User not found";
        }
        $stmt->close();
    }
    
    // Validate new password
    if (empty($newPassword)) {
        $errors[] = "New password is required";
    } elseif (strlen($newPassword) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    // Validate password confirmation
    if ($newPassword !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, update password
    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE User SET password = ? WHERE userId = ?");
        $stmt->bind_param("si", $hashedPassword, $userInfo['userId']);
        
        if ($stmt->execute()) {
            // Log the password reset activity
            $activityMsg = "Password reset completed on " . date("m/d/Y H:i:s");
            $activityStmt = $conn->prepare("UPDATE User SET userActivities = CONCAT(IFNULL(userActivities, ''), '\n', ?) WHERE userId = ?");
            $activityStmt->bind_param("si", $activityMsg, $userInfo['userId']);
            $activityStmt->execute();
            $activityStmt->close();
            
            // Remove the used token
            unset($_SESSION['reset_tokens'][$token]);
            
            $success = "Your password has been successfully reset. You can now log in with your new password.";
        } else {
            $errors[] = "Failed to update password. Please try again.";
        }
        $stmt->close();
    }
}

$pageTitle = "Reset Password";
include_once '../includes/header.php';
?>

<div class="container py-5 main-container">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="auth-form">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-key fa-3x text-primary"></i>
                    </div>
                    <h2 class="form-title">Reset Password</h2>
                    <?php if ($userInfo): ?>
                        <p class="text-muted">Hi <?php echo htmlspecialchars($userInfo['firstname']); ?>, please answer your security question to reset your password</p>
                    <?php else: ?>
                        <p class="text-muted">Reset your password</p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-check-circle me-2"></i>
                            <div><?php echo $success; ?></div>
                        </div>
                    </div>
                    <div class="text-center">
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                        </a>
                    </div>
                <?php elseif ($userInfo): ?>
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?token=<?php echo htmlspecialchars($token); ?>" method="POST" novalidate>
                        <!-- Security Question -->
                        <div class="mb-4">
                            <label class="form-label">Security Question</label>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-question-circle text-primary me-2"></i>
                                        <span><?php echo htmlspecialchars($userInfo['securityQuestion']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security Answer -->
                        <div class="mb-4">
                            <label for="securityAnswer" class="form-label">Your Answer <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-key text-primary"></i>
                                </span>
                                <input type="text" class="form-control" id="securityAnswer" name="securityAnswer" value="<?php echo htmlspecialchars($securityAnswer); ?>" placeholder="Enter your answer" required>
                            </div>
                            <div class="form-text">Answer is case insensitive</div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- New Password -->
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-lock text-primary"></i>
                                </span>
                                <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                                <span class="input-group-text bg-white cursor-pointer" onclick="togglePassword('newPassword')">
                                    <i class="fas fa-eye text-primary" id="newPassword-toggle-icon"></i>
                                </span>
                            </div>
                            <div class="form-text">At least 8 characters</div>
                        </div>
                        
                        <!-- Confirm New Password -->
                        <div class="mb-4">
                            <label for="confirmPassword" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-check-double text-primary"></i>
                                </span>
                                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                                <span class="input-group-text bg-white cursor-pointer" onclick="togglePassword('confirmPassword')">
                                    <i class="fas fa-eye text-primary" id="confirmPassword-toggle-icon"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mb-4">
                            <button type="submit" class="btn btn-primary py-2">
                                <i class="fas fa-save me-2"></i>Reset Password
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div>Invalid or expired reset token. Please request a new password reset.</div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="text-center">
                    <p class="mb-0">Remember your password? <a href="login.php" class="text-primary">Sign In</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = document.getElementById(inputId + '-toggle-icon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}
</script>

<?php include_once '../includes/footer.php'; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
.cursor-pointer {
    cursor: pointer;
}
</style>