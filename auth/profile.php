<?php
// auth/profile.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$userId = $_SESSION['userId'];
$role = $_SESSION['role'];
$successMsg = '';
$errorMsg = '';

$stmt = $conn->prepare("SELECT * FROM User WHERE userId = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
} else {
    $errorMsg = "User not found";
}
$stmt->close();

$merchantData = null;
if ($role === 'merchant') {
    $stmtMerchant = $conn->prepare("SELECT * FROM Merchant WHERE userId = ?");
    $stmtMerchant->bind_param("i", $userId);
    $stmtMerchant->execute();
    $resultMerchant = $stmtMerchant->get_result();
    
    if ($resultMerchant->num_rows === 1) {
        $merchantData = $resultMerchant->fetch_assoc();
    }
    $stmtMerchant->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $contactNum = trim($_POST['contactNum']);
    $address = trim($_POST['address']);
    
    $errors = [];
    
    if (empty($firstname)) {
        $errors[] = "First name is required";
    }
    
    if (empty($lastname)) {
        $errors[] = "Last name is required";
    }
    
    if (empty($contactNum)) {
        $errors[] = "Contact number is required";
    } elseif (!preg_match('/^\+?[0-9]{10,15}$/', $contactNum)) {
        $errors[] = "Contact number format is invalid";
    }
    
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    
    if (empty($errors)) {
        $updateStmt = $conn->prepare("UPDATE User SET firstname = ?, lastname = ?, contactNum = ?, address = ? WHERE userId = ?");
        $updateStmt->bind_param("ssssi", $firstname, $lastname, $contactNum, $address, $userId);
        
        if ($updateStmt->execute()) {
            $_SESSION['firstName'] = $firstname;
            
            $activity = "Updated profile information on " . date('Y-m-d H:i:s');
            $activityStmt = $conn->prepare("UPDATE User SET userActivities = CONCAT(userActivities, '\n', ?) WHERE userId = ?");
            $activityStmt->bind_param("si", $activity, $userId);
            $activityStmt->execute();
            $activityStmt->close();
            
            $successMsg = "Profile updated successfully";
            
            $stmt = $conn->prepare("SELECT * FROM User WHERE userId = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
        } else {
            $errorMsg = "Failed to update profile: " . $conn->error;
        }
        
        $updateStmt->close();
    } else {
        $errorMsg = implode("<br>", $errors);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = trim($_POST['currentPassword']);
    $newPassword = trim($_POST['newPassword']);
    $confirmPassword = trim($_POST['confirmPassword']);
    
    $errors = [];
    
    if (empty($currentPassword)) {
        $errors[] = "Current password is required";
    }
    
    if (empty($newPassword)) {
        $errors[] = "New password is required";
    } elseif (strlen($newPassword) < 8) {
        $errors[] = "New password must be at least 8 characters long";
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = "New passwords do not match";
    }
    
    if (empty($errors)) {
        if (!password_verify($currentPassword, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
    }
    
    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $updateStmt = $conn->prepare("UPDATE User SET password = ? WHERE userId = ?");
        $updateStmt->bind_param("si", $hashedPassword, $userId);
        
        if ($updateStmt->execute()) {
            $activity = "Changed password on " . date('Y-m-d H:i:s');
            $activityStmt = $conn->prepare("UPDATE User SET userActivities = CONCAT(userActivities, '\n', ?) WHERE userId = ?");
            $activityStmt->bind_param("si", $activity, $userId);
            $activityStmt->execute();
            $activityStmt->close();
            
            $successMsg = "Password changed successfully";
        } else {
            $errorMsg = "Failed to change password: " . $conn->error;
        }
        
        $updateStmt->close();
    } else {
        $errorMsg = implode("<br>", $errors);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_store']) && $role === 'merchant') {
    $storeName = trim($_POST['storeName']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($storeName)) {
        $errors[] = "Store name is required";
    }
    
    if (empty($errors)) {
        $updateStmt = $conn->prepare("UPDATE Merchant SET storeName = ? WHERE userId = ?");
        $updateStmt->bind_param("si", $storeName, $userId);
        
        if ($updateStmt->execute()) {
            // Update activity log
            $activity = "Updated store details on " . date('Y-m-d H:i:s');
            $activityStmt = $conn->prepare("UPDATE User SET userActivities = CONCAT(userActivities, '\n', ?) WHERE userId = ?");
            $activityStmt->bind_param("si", $activity, $userId);
            $activityStmt->execute();
            $activityStmt->close();
            
            $successMsg = "Store details updated successfully";
            
            // Refresh merchant data
            $stmtMerchant = $conn->prepare("SELECT * FROM Merchant WHERE userId = ?");
            $stmtMerchant->bind_param("i", $userId);
            $stmtMerchant->execute();
            $resultMerchant = $stmtMerchant->get_result();
            $merchantData = $resultMerchant->fetch_assoc();
            $stmtMerchant->close();
        } else {
            $errorMsg = "Failed to update store details: " . $conn->error;
        }
        
        $updateStmt->close();
    } else {
        $errorMsg = implode("<br>", $errors);
    }
}

//  profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture'])) {
    if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profilePicture']['name'];
        $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($fileExt, $allowed)) {
            $uploadDir = '../assets/images/profiles/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $newFilename = 'user_' . $userId . '_' . time() . '.' . $fileExt;
            $destination = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['profilePicture']['tmp_name'], $destination)) {
                $relativePath = 'assets/images/profiles/' . $newFilename;
                $updateStmt = $conn->prepare("UPDATE User SET profilePicture = ? WHERE userId = ?");
                $updateStmt->bind_param("si", $relativePath, $userId);
                
                if ($updateStmt->execute()) {
                    $activity = "Updated profile picture on " . date('Y-m-d H:i:s');
                    $activityStmt = $conn->prepare("UPDATE User SET userActivities = CONCAT(userActivities, '\n', ?) WHERE userId = ?");
                    $activityStmt->bind_param("si", $activity, $userId);
                    $activityStmt->execute();
                    $activityStmt->close();
                    
                    $successMsg = "Profile picture updated successfully";
                    
                    $stmt = $conn->prepare("SELECT * FROM User WHERE userId = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $stmt->close();
                } else {
                    $errorMsg = "Failed to update profile picture in database: " . $conn->error;
                }
                
                $updateStmt->close();
            } else {
                $errorMsg = "Failed to upload profile picture";
            }
        } else {
            $errorMsg = "Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed";
        }
    } else {
        $errorMsg = "Error uploading file: " . $_FILES['profilePicture']['error'];
    }
}

include_once '../includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<style>
    :root {
        --primary-color: #0d6efd;
        --secondary-color: #0a4db5;
        --dark-color: #212529;
        --light-color: #f8f9fa;
        --border-radius: 10px;
        --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    body {
        background-color: #f0f2f5;
    }
    
    .profile-card {
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
        margin-bottom: 25px;
    }
    
    .profile-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }
    
    .card-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 15px 20px;
        border-bottom: none;
    }
    
    .profile-picture-container {
        position: relative;
        width: 150px;
        height: 150px;
        margin: 0 auto;
        border-radius: 50%;
        overflow: hidden;
        border: 5px solid white;
        box-shadow: var(--box-shadow);
    }
    
    .profile-picture {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .form-control {
        border-radius: 7px;
        padding: 10px 15px;
        border: 1px solid #ced4da;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    .btn-profile {
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 7px;
        padding: 10px 20px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-profile:hover {
        background: var(--secondary-color);
        transform: translateY(-2px);
    }
    
    .file-upload {
        position: relative;
        overflow: hidden;
        margin: 10px 0;
    }
    
    .file-upload input[type=file] {
        position: absolute;
        top: 0;
        right: 0;
        min-width: 100%;
        min-height: 100%;
        font-size: 100px;
        text-align: right;
        filter: alpha(opacity=0);
        opacity: 0;
        outline: none;
        cursor: pointer;
        display: block;
    }
    
    .fade-in {
        animation: fadeIn 0.5s ease forwards;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .readonly-field {
        background-color: #f8f9fa;
        cursor: not-allowed;
    }
    
    .alert {
        border-radius: var(--border-radius);
        animation: slideDown 0.5s ease forwards;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .section-title {
        margin-bottom: 20px;
        font-weight: 600;
        color: var(--dark-color);
    }
    
    .field-icon {
        color: var(--primary-color);
        margin-right: 10px;
    }
</style>

<div class="container py-5 fade-in">
    <h1 class="text-center mb-5 section-title">My Profile</h1>
    
    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $successMsg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $errorMsg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Left Sidebar with Profile Picture -->
        <div class="col-lg-4 mb-4">
            <div class="profile-card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-person-circle me-2"></i>Profile Picture</h4>
                </div>
                <div class="card-body text-center py-4">
                    <div class="profile-picture-container mb-4">
                        <?php if (!empty($user['profilePicture'])): ?>
                            <img src="../<?php echo htmlspecialchars($user['profilePicture']); ?>" alt="Profile Picture" class="profile-picture">
                        <?php else: ?>
                            <img src="../assets/images/profile-placeholder.jpg" alt="Default Profile" class="profile-picture">
                        <?php endif; ?>
                    </div>
                    
                    <h5 class="mb-3"><?php echo htmlspecialchars($user['firstname']) . ' ' . htmlspecialchars($user['lastname']); ?></h5>
                    <p class="text-muted"><i class="bi bi-person-badge me-2"></i><?php echo ucfirst(htmlspecialchars($user['role'])); ?></p>
                    
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" class="mt-4">
                        <div class="mb-3">
                            <label for="profilePicture" class="form-label d-block">
                                <div class="btn btn-outline-primary w-100">
                                    <i class="bi bi-upload me-2"></i>Choose New Picture
                                </div>
                            </label>
                            <input type="file" class="form-control d-none" id="profilePicture" name="profilePicture" accept="image/*" required>
                            <div id="selected-file" class="mt-2 text-muted small">No file selected</div>
                        </div>
                        <button type="submit" name="upload_picture" class="btn btn-profile w-100">
                            <i class="bi bi-arrow-up-circle me-2"></i>Upload Picture
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="profile-card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Stats</h4>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span><i class="bi bi-calendar3 field-icon"></i>Member Since</span>
                        <span class="badge bg-primary"><?php echo date('M d, Y', strtotime($user['dateCreated'])); ?></span>
                    </div>
                    
                    <?php if ($role === 'merchant'): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-people field-icon"></i>Followers</span>
                        <span class="badge bg-primary"><?php echo htmlspecialchars($user['followers'] ?? '0'); ?></span>
                    </div>
                    <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-person-plus field-icon"></i>Following</span>
                        <span class="badge bg-primary"><?php echo htmlspecialchars($user['following'] ?? '0'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="col-lg-8">
            <!-- Account Information Card -->
            <div class="profile-card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-person-vcard me-2"></i>Account Information</h4>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="username" class="form-label">
                                    <i class="bi bi-at field-icon"></i>Username
                                </label>
                                <input type="text" class="form-control readonly-field" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">
                                    <i class="bi bi-person-gear field-icon"></i>Account Type
                                </label>
                                <input type="text" class="form-control readonly-field" id="role" value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="firstname" class="form-label">
                                    <i class="bi bi-person field-icon"></i>First Name
                                </label>
                                <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="lastname" class="form-label">
                                    <i class="bi bi-person field-icon"></i>Last Name
                                </label>
                                <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="birthday" class="form-label">
                                    <i class="bi bi-calendar-event field-icon"></i>Birthday
                                </label>
                                <input type="text" class="form-control readonly-field" id="birthday" value="<?php echo htmlspecialchars($user['birthday']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="sex" class="form-label">
                                    <i class="bi bi-gender-ambiguous field-icon"></i>Gender
                                </label>
                                <input type="text" class="form-control readonly-field" id="sex" value="<?php echo ($user['sex'] === 'M') ? 'Male' : 'Female'; ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contactNum" class="form-label">
                                <i class="bi bi-telephone field-icon"></i>Contact Number
                            </label>
                            <input type="tel" class="form-control" id="contactNum" name="contactNum" value="<?php echo htmlspecialchars($user['contactNum']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">
                                <i class="bi bi-geo-alt field-icon"></i>Address
                            </label>
                            <textarea class="form-control" id="address" name="address" rows="2" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-profile">
                            <i class="bi bi-check-circle me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if ($role === 'merchant' && $merchantData): ?>
            <!-- Store Details Card -->
            <div class="profile-card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-shop me-2"></i>Store Details</h4>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="mb-3">
                            <label for="storeName" class="form-label">
                                <i class="bi bi-building field-icon"></i>Store Name
                            </label>
                            <input type="text" class="form-control" id="storeName" name="storeName" value="<?php echo htmlspecialchars($merchantData['storeName']); ?>" required>
                        </div>
                        
                        <button type="submit" name="update_store" class="btn btn-profile">
                            <i class="bi bi-check-circle me-2"></i>Update Store Details
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Password Change Card -->
            <div class="profile-card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Change Password</h4>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">
                                <i class="bi bi-key field-icon"></i>Current Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="currentPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">
                                <i class="bi bi-key-fill field-icon"></i>New Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="newPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">
                                <i class="bi bi-check-circle field-icon"></i>Confirm New Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirmPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-profile">
                            <i class="bi bi-arrow-repeat me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Add animation to cards
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.profile-card');
        
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.classList.add('animate__animated', 'animate__fadeIn');
            }, index * 150);
        });
        
        const fileInput = document.getElementById('profilePicture');
        const fileDisplay = document.getElementById('selected-file');
        
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                fileDisplay.textContent = this.files[0].name;
            } else {
                fileDisplay.textContent = 'No file selected';
            }
        });
        
        // toggle password visibility
        const toggleButtons = document.querySelectorAll('.toggle-password');
        
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const inputField = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (inputField.type === 'password') {
                    inputField.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    inputField.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            });
        });
        
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const closeButton = alert.querySelector('.btn-close');
                if (closeButton) {
                    closeButton.click();
                }
            }, 5000);
        });
    });
</script>

<?php include_once '../includes/footer.php'; ?>