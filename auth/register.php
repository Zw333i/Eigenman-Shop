<?php
// auth/register.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['userId'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/functions.php';
require_once '../config/database.php';

$username = "";
$firstname = "";
$lastname = "";
$sex = "";
$birthday = "";
$contactNum = "";
$address = "";
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $role = trim($_POST['role'] ?? 'customer');
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $birthday = trim($_POST['birthday'] ?? '');
    $contactNum = trim($_POST['contactNum'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    $profilePicture = null;
    if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/profile_pictures/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['profilePicture']['name']);
        $targetFile = $uploadDir . $fileName;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        
        $check = getimagesize($_FILES['profilePicture']['tmp_name']);
        if ($check === false) {
            $errors[] = "File is not an image.";
        }
        
        if ($_FILES['profilePicture']['size'] > 5000000) {
            $errors[] = "File is too large. Max size is 5MB.";
        }
        
        // Allow certain file formats
        if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed.";
        }
        
        // If no errors, try to upload file
        if (empty($errors)) {
            if (move_uploaded_file($_FILES['profilePicture']['tmp_name'], $targetFile)) {
                $profilePicture = $fileName;
            } else {
                $errors[] = "There was an error uploading your file.";
            }
        }
    }
    
    if (empty($username)) {
        $errors[] = "Username is required";
    } else {
        $stmt = $conn->prepare("SELECT userId FROM User WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Username already exists";
        }
        $stmt->close();
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($firstname)) {
        $errors[] = "First name is required";
    }
    
    if (empty($lastname)) {
        $errors[] = "Last name is required";
    }
    
    if (empty($sex)) {
        $errors[] = "Sex is required";
    } elseif (!in_array($sex, ['M', 'F'])) {
        $errors[] = "Sex must be 'M' or 'F'";
    }
    
    if (empty($birthday)) {
        $errors[] = "Birthday is required";
    }
    
    if (empty($contactNum)) {
        $errors[] = "Contact number is required";
    }
    
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    
    if (empty($errors)) {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $followers = ($role === 'merchant') ? 0 : NULL;
        $following = ($role === 'customer') ? 0 : NULL;
        $userActivities = "Account created on " . date("m/d/Y H:i:s");
        $dateCreated = date("Y-m-d H:i:s");
        
        $stmt = $conn->prepare("INSERT INTO User (username, password, role, firstname, lastname, sex, birthday, contactNum, address, followers, following, dateCreated, userActivities, profilePicture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssssss", $username, $hashedPassword, $role, $firstname, $lastname, $sex, $birthday, $contactNum, $address, $followers, $following, $dateCreated, $userActivities, $profilePicture);
        
        if ($stmt->execute()) {
            $userId = $stmt->insert_id;
            
            if ($role === 'merchant') {
                $storeName = $firstname . "'s Store"; 
                $merchantStmt = $conn->prepare("INSERT INTO Merchant (merchantId, userId, storeName) VALUES (?, ?, ?)");
                $merchantStmt->bind_param("iis", $userId, $userId, $storeName);
                $merchantStmt->execute();
                $merchantStmt->close();
            }
            
            $_SESSION['userId'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['firstname'] = $firstname;
            
            header("Location: ../index.php");
            exit();
        } else {
            $errors[] = "Registration failed: " . $conn->error;
        }
        
        $stmt->close();
    }
}

$pageTitle = "Register";
include_once '../includes/header.php';
?>

<div class="container py-5 main-container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="auth-form">
                <div class="text-center mb-4">
                    <h2 class="form-title">Create Your Account</h2>
                    <p class="text-muted">Join our community today</p>
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
                
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data" novalidate>
                    <!-- Account Type -->
                    <div class="mb-4">
                        <label class="form-label">Account Type</label>
                        <div class="d-flex">
                            <div class="form-check form-check-inline flex-grow-1">
                                <input class="form-check-input" type="radio" name="role" id="roleCustomer" value="customer" <?php echo (!isset($role) || $role === 'customer') ? 'checked' : ''; ?>>
                                <label class="form-check-label w-100 p-3 border rounded-3 <?php echo (!isset($role) || $role === 'customer') ? 'border-primary bg-light-blue' : ''; ?>" for="roleCustomer">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user fa-lg me-2 text-primary"></i>
                                        <div>
                                            <strong>Customer</strong>
                                            <div class="small text-muted">Shop products and services</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div class="form-check form-check-inline flex-grow-1">
                                <input class="form-check-input" type="radio" name="role" id="roleMerchant" value="merchant" <?php echo (isset($role) && $role === 'merchant') ? 'checked' : ''; ?>>
                                <label class="form-check-label w-100 p-3 border rounded-3 <?php echo (isset($role) && $role === 'merchant') ? 'border-primary bg-light-blue' : ''; ?>" for="roleMerchant">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-store fa-lg me-2 text-primary"></i>
                                        <div>
                                            <strong>Merchant</strong>
                                            <div class="small text-muted">Sell your products</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Picture -->
                    <div class="mb-4">
                        <label for="profilePicture" class="form-label">Profile Picture</label>
                        <div class="d-flex flex-column align-items-center mb-3">
                            <div class="profile-picture-preview" id="profilePicturePreview">
                                <img src="../assets/images/default-profile.jpg" alt="Profile Preview" class="img-fluid rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                            </div>
                            <div class="mt-2">
                                <label for="profilePictureInput" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-camera me-1"></i> Choose Photo
                                </label>
                                <input type="file" class="d-none" id="profilePictureInput" name="profilePicture" accept="image/*" onchange="previewProfilePicture(this)">
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="border-bottom pb-2 mb-3 text-dark-blue">Account Information</h5>
                    
                    <!-- Username -->
                    <div class="mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-at text-primary"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                        </div>
                    </div>
                    
                    <!-- Password and Confirm Password in a new row for more space -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-lock text-primary"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <span class="input-group-text bg-white cursor-pointer" onclick="togglePassword('password')">
                                    <i class="fas fa-eye text-primary" id="password-toggle-icon"></i>
                                </span>
                            </div>
                            <div class="form-text">At least 8 characters</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password <span class="text-danger">*</span></label>
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
                    </div>
                    
                    <h5 class="border-bottom pb-2 mb-3 mt-4 text-dark-blue">Personal Information</h5>
                    
                    <!-- Name -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="firstname" class="form-label">First Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-user text-primary"></i>
                                </span>
                                <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo htmlspecialchars($firstname); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="lastname" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-user text-primary"></i>
                                </span>
                                <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo htmlspecialchars($lastname); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sex and Birthday -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sex <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check form-check-inline flex-grow-1">
                                    <input class="form-check-input" type="radio" name="sex" id="sexM" value="M" <?php echo ($sex === 'M') ? 'checked' : ''; ?> required>
                                    <label class="form-check-label w-100 p-2 border rounded <?php echo ($sex === 'M') ? 'border-primary bg-light-blue' : ''; ?>" for="sexM">
                                        <i class="fas fa-mars me-2 text-primary"></i> Male
                                    </label>
                                </div>
                                <div class="form-check form-check-inline flex-grow-1">
                                    <input class="form-check-input" type="radio" name="sex" id="sexF" value="F" <?php echo ($sex === 'F') ? 'checked' : ''; ?> required>
                                    <label class="form-check-label w-100 p-2 border rounded <?php echo ($sex === 'F') ? 'border-primary bg-light-blue' : ''; ?>" for="sexF">
                                        <i class="fas fa-venus me-2 text-primary"></i> Female
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="birthday" class="form-label">Birthday <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-calendar-alt text-primary"></i>
                                </span>
                                <input type="date" class="form-control" id="birthday" name="birthday" value="<?php echo htmlspecialchars($birthday); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Info -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contactNum" class="form-label">Contact Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fas fa-phone text-primary"></i>
                                </span>
                                <input type="tel" class="form-control" id="contactNum" name="contactNum" value="<?php echo htmlspecialchars($contactNum); ?>" placeholder="+63xxxxxxxxxx" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address -->
                    <div class="mb-4">
                        <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-map-marker-alt text-primary"></i>
                            </span>
                            <textarea class="form-control" id="address" name="address" rows="2" required><?php echo htmlspecialchars($address); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Terms and Privacy -->
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="agreeTerms" name="agreeTerms" required>
                            <label class="form-check-label" for="agreeTerms">
                                I agree to the <a href="#" class="text-primary">Terms of Service</a> and <a href="#" class="text-primary">Privacy Policy</a>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="d-grid gap-2 mb-4">
                        <button type="submit" class="btn btn-primary py-2">Create Account</button>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-0">Already have an account? <a href="login.php" class="text-primary">Sign In</a></p>
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

function previewProfilePicture(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const previewImg = document.querySelector('#profilePicturePreview img');
            previewImg.src = e.target.result;
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

document.querySelectorAll('input[name="role"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.form-check-label[for^="role"]').forEach(label => {
            label.classList.remove('border-primary', 'bg-light-blue');
        });
        
        if (this.checked) {
            document.querySelector(`.form-check-label[for="role${this.value.charAt(0).toUpperCase() + this.value.slice(1)}"]`)
                .classList.add('border-primary', 'bg-light-blue');
        }
    });
});

document.querySelectorAll('input[name="sex"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.form-check-label[for^="sex"]').forEach(label => {
            label.classList.remove('border-primary', 'bg-light-blue');
        });
        
        if (this.checked) {
            document.querySelector(`.form-check-label[for="sex${this.value}"]`)
                .classList.add('border-primary', 'bg-light-blue');
        }
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
.cursor-pointer {
    cursor: pointer;
}
</style>