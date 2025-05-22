<?php
// auth/login.php


if (session_status() === PHP_SESSION_NONE) {
    
    session_start();
    
    
}

require_once '../config/database.php';

if (isset($_COOKIE['user_id']) && !isset($_SESSION['userId'])) {
    $user_id = $_COOKIE['user_id'];
    $stmt = $conn->prepare("SELECT userId, username, role, firstname FROM User WHERE userId = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $_SESSION['userId'] = $user['userId'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['firstname'] = $user['firstname'];
        redirectBasedOnRole($user['role']);
        exit();
    }
}


if (isset($_SESSION['userId'])) {
    redirectBasedOnRole($_SESSION['role']);
    exit();
}

require_once '../includes/functions.php';


$username = "";
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT userId, username, password, role, firstname FROM User WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['userId'] = $user['userId'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['firstname'] = $user['firstname'];
                setcookie("user_id", $user['userId'], time() + (30 * 24 * 60 * 60), "/");

                
                $profileQuery = $conn->prepare("SELECT profilePicture FROM User WHERE userId = ?");
                $profileQuery->bind_param("i", $user['userId']);
                $profileQuery->execute();
                $profileResult = $profileQuery->get_result();
                if ($profileResult->num_rows === 1) {
                    $profileData = $profileResult->fetch_assoc();
                    if (!empty($profileData['profilePic'])) {
                        $_SESSION['profile_pic'] = $profileData['profilePic'];
                    }
                }
                $profileQuery->close();
                
                $activityMsg = "Logged in on " . date("m/d/Y H:i:s");
                $updateStmt = $conn->prepare("UPDATE User SET userActivities = CONCAT(userActivities, '\n', ?) WHERE userId = ?");
                $updateStmt->bind_param("si", $activityMsg, $user['userId']);
                $updateStmt->execute();
                $updateStmt->close();
                
                redirectBasedOnRole($user['role']);
                exit();
            } else {
                $errors[] = "Invalid username or password";
            }
        } else {
            $errors[] = "Invalid username or password";
        }
        
        $stmt->close();
    }
}

function redirectBasedOnRole($role) {
    switch ($role) {
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

$pageTitle = "Login";
include_once '../includes/header.php';
?>

<div class="container py-5 main-container">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="auth-form">
                <div class="text-center mb-4">
                    <h2 class="form-title">Welcome Back</h2>
                    <p class="text-muted">Sign in to your account</p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" novalidate>
                    <div class="mb-4">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-user text-primary"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" placeholder="Enter your username" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between">
                            <label for="password" class="form-label">Password</label>
                            <a href="forgot-password.php" class="text-primary small">Forgot Password?</a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-lock text-primary"></i> 
                            </span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                            <span class="input-group-text bg-white cursor-pointer" onclick="togglePassword('password')">
                                <i class="fas fa-eye text-primary" id="password-toggle-icon"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                          
                    
                    </div>
                    
                    <div class="d-grid gap-2 mb-4">
                        <button type="submit" class="btn btn-primary py-2">Sign In</button>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-0">Don't have an account? <a href="register.php" class="text-primary">Create Account</a></p>
                    </div>
                </form>
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