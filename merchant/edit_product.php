<?php
// merchant/edit_product
require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/navbar.php';

if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'merchant') {
    header("Location: /e-commerce/auth/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: /e-commerce/merchant/products.php");
    exit();
}

$productId = $_GET['id'];
$merchantId = $_SESSION['userId'];

$query = "SELECT * FROM Item WHERE itemId = ? AND merchantId = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $productId, $merchantId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: /e-commerce/merchant/products.php");
    exit();
}

$product = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemName = $_POST['itemName'];
    $brand = $_POST['brand'];
    $itemPrice = $_POST['itemPrice'];
    $quantity = $_POST['quantity'];
    
    $picturePath = $product['picture'];
    
    if (!empty($_FILES['picture']['name'])) {
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/uploads/';
        $fileName = uniqid() . '_' . basename($_FILES['picture']['name']);
        $targetFile = $targetDir . $fileName;
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        
        $check = getimagesize($_FILES['picture']['tmp_name']);
        if ($check === false) {
            $error = "File is not an image.";
            $uploadOk = 0;
        }
        
        if ($_FILES['picture']['size'] > 5000000) {
            $error = "Sorry, your file is too large.";
            $uploadOk = 0;
        }
        
        if (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }
        
        if ($uploadOk) {
            if (move_uploaded_file($_FILES['picture']['tmp_name'], $targetFile)) {
                if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/e-commerce/' . $product['picture'])) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . '/e-commerce/' . $product['picture']);
                }
                $picturePath = 'assets/uploads/' . $fileName;
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        }
    }
    
    if (!isset($error)) {
        $query = "UPDATE Item SET itemName = ?, picture = ?, brand = ?, itemPrice = ?, quantity = ? 
                  WHERE itemId = ? AND merchantId = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssdiii", $itemName, $picturePath, $brand, $itemPrice, $quantity, $productId, $merchantId);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Product updated successfully!";
            header("Location: /e-commerce/merchant/products.php");
            exit();
        } else {
            $error = "Error updating product in database.";
        }
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>Edit Product</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="itemName" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="itemName" name="itemName" 
                                   value="<?php echo htmlspecialchars($product['itemName']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="brand" class="form-label">Brand</label>
                            <input type="text" class="form-control" id="brand" name="brand" 
                                   value="<?php echo htmlspecialchars($product['brand']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="itemPrice" class="form-label">Price</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" class="form-control" id="itemPrice" name="itemPrice" 
                                       value="<?php echo htmlspecialchars($product['itemPrice']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" 
                                   value="<?php echo htmlspecialchars($product['quantity']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="picture" class="form-label">Product Image</label>
                            <input type="file" class="form-control" id="picture" name="picture" accept="image/*">
                            <div class="form-text">Current image: 
                                <a href="<?php echo $basePath . htmlspecialchars($product['picture']); ?>" target="_blank">
                                    <?php echo basename($product['picture']); ?>
                                </a>
                            </div>
                            <img src="<?php echo $basePath . htmlspecialchars($product['picture']); ?>" 
                                 class="img-thumbnail mt-2" style="max-height: 150px;">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Update Product</button>
                            <a href="/e-commerce/merchant/products.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/footer.php'; ?>