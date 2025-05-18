<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'merchant') {

    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$itemName = $brand = '';
$itemPrice = $quantity = 0;
$errors = [];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemName = trim($_POST['itemName']);
    $brand = trim($_POST['brand']);
    $itemPrice = trim($_POST['itemPrice']);
    $quantity = trim($_POST['quantity']);
    
    if (empty($itemName)) {
        $errors[] = "Product name is required";
    }
    
    if (empty($brand)) {
        $errors[] = "Brand is required";
    }
    
    if (empty($itemPrice)) {
        $errors[] = "Price is required";
    } elseif (!is_numeric($itemPrice) || $itemPrice <= 0) {
        $errors[] = "Price must be a positive number";
    }
    
    if (empty($quantity)) {
        $errors[] = "Quantity is required";
    } elseif (!is_numeric($quantity) || $quantity < 0 || !is_int((int)$quantity)) {
        $errors[] = "Quantity must be a non-negative whole number";
    }
    
    $picture = '';
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['picture']['name'];
        $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($fileExt, $allowed)) {
            $uploadDir = '../assets/images/products/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $newFilename = 'product_' . time() . '_' . uniqid() . '.' . $fileExt;
            $destination = $uploadDir . $newFilename;
            
            if (move_uploaded_file($_FILES['picture']['tmp_name'], $destination)) {
                $picture = 'assets/images/products/' . $newFilename;
            } else {
                $errors[] = "Failed to upload image";
            }
        } else {
            $errors[] = "Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed";
        }
    } else {
        $errors[] = "Product image is required";
    }
    
    if (empty($errors)) {
        $merchantId = $_SESSION['userId']; 
        
        $stmt = $conn->prepare("INSERT INTO Item (merchantId, itemName, picture, brand, itemPrice, quantity) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssdi", $merchantId, $itemName, $picture, $brand, $itemPrice, $quantity);
        
        if ($stmt->execute()) {
            $itemId = $conn->insert_id;
            
            $activity = "Added new product: {$itemName} on " . date('Y-m-d H:i:s');
            $updateActivity = $conn->prepare("UPDATE User SET userActivities = CONCAT(userActivities, '\n', ?) WHERE userId = ?");
            $updateActivity->bind_param("si", $activity, $merchantId);
            $updateActivity->execute();
            $updateActivity->close();
            
            $successMsg = "Product added successfully!";
            
            $itemName = $brand = '';
            $itemPrice = $quantity = 0;
        } else {
            $errors[] = "Failed to add product: " . $conn->error;
        }
        
        $stmt->close();
    }
}

include_once '../includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Add New Product</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($successMsg)): ?>
                        <div class="alert alert-success"><?php echo $successMsg; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="itemName" class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="itemName" name="itemName" value="<?php echo htmlspecialchars($itemName); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="brand" class="form-label">Brand <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="brand" name="brand" value="<?php echo htmlspecialchars($brand); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="itemPrice" class="form-label">Price (₱) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="itemPrice" name="itemPrice" min="0.01" step="0.01" value="<?php echo htmlspecialchars($itemPrice); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="quantity" class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="quantity" name="quantity" min="0" step="1" value="<?php echo htmlspecialchars($quantity); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="picture" class="form-label">Product Image <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="picture" name="picture" accept="image/*" required>
                            <small class="text-muted">Supported formats: JPG, JPEG, PNG, GIF</small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Add Product</button>
                            <a href="../merchant/products.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>