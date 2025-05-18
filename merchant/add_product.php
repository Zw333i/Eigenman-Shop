<?php
// merchant/add_product.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/config/database.php';

if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'merchant') {
    header("Location: /e-commerce/auth/login.php");
    exit();
}

$error = '';
$itemName = $brand = $itemPrice = $quantity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemName = trim($_POST['itemName'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $itemPrice = trim($_POST['itemPrice'] ?? '');
    $quantity = trim($_POST['quantity'] ?? '');
    $merchantId = $_SESSION['userId'];
    
    if (empty($itemName) || empty($brand) || empty($itemPrice) || empty($quantity)) {
        $error = "All fields are required.";
    } elseif (!is_numeric($itemPrice) || $itemPrice <= 0) {
        $error = "Please enter a valid price.";
    } elseif (!is_numeric($quantity) || $quantity <= 0) {
        $error = "Please enter a valid quantity.";
    } elseif (!isset($_FILES['picture']) || $_FILES['picture']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please select a valid image file.";
    } else {
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/products/';
        $fileName = uniqid() . '_' . basename($_FILES['picture']['name']);
        $targetFile = $targetDir . $fileName;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        
        $check = getimagesize($_FILES['picture']['tmp_name']);
        if ($check === false) {
            $error = "File is not an image.";
        } elseif ($_FILES['picture']['size'] > 5000000) {
            $error = "Sorry, your file is too large (max 5MB).";
        } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        } else {
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            if (move_uploaded_file($_FILES['picture']['tmp_name'], $targetFile)) {
                $query = "INSERT INTO item (merchantId, itemName, picture, brand, itemPrice, quantity) 
                          VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $picturePath = 'assets/images/products/' . $fileName;
                $stmt->bind_param("isssdi", $merchantId, $itemName, $picturePath, $brand, $itemPrice, $quantity);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Product added successfully!";
                    header("Location: /e-commerce/merchant/products.php");
                    exit();
                } else {
                    $error = "Error saving product to database: " . $conn->error;
                    error_log("Database error: " . $stmt->error); 
                }
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        }
    }
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/navbar.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>Add New Product</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="itemName" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="itemName" name="itemName" 
                                   value="<?php echo htmlspecialchars($itemName); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="brand" class="form-label">Brand</label>
                            <input type="text" class="form-control" id="brand" name="brand" 
                                   value="<?php echo htmlspecialchars($brand); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="itemPrice" class="form-label">Price</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" id="itemPrice" name="itemPrice" 
                                       value="<?php echo htmlspecialchars($itemPrice); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" 
                                   value="<?php echo htmlspecialchars($quantity); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="picture" class="form-label">Product Image</label>
                            <input type="file" class="form-control" id="picture" name="picture" accept="image/*" required>
                            <div class="form-text">Upload a clear image of your product (max 5MB)</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Add Product</button>
                            <a href="/e-commerce/merchant/products.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/footer.php'; ?>