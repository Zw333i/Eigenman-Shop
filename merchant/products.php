<?php
// merchant/products.php
session_start();

// Check if user is logged in and is a merchant
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'merchant') {
    header("Location: /e-commerce/auth/login.php");
    exit();
}

// Include database connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/config/database.php';

$userId = $_SESSION['userId'];
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$success_message = '';
$error_message = '';

// Process form submission for adding or updating products
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product']) || isset($_POST['update_product'])) {
        $itemName = $conn->real_escape_string($_POST['itemName']);
        $brand = $conn->real_escape_string($_POST['brand']);
        $itemPrice = floatval($_POST['itemPrice']);
        $quantity = intval($_POST['quantity']);
        $description = $conn->real_escape_string($_POST['description'] ?? ''); // Get optional description
        
        // Handle file upload
        $picture = "";
        $upload_success = true;
        
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] == 0) {
            $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/e-commerce/assets/images/products/";
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('product_') . '.' . $file_extension;
            $target_file = $target_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['picture']['tmp_name'], $target_file)) {
                $picture = "assets/images/products/" . $filename;
            } else {
                $upload_success = false;
                $error_message = "Sorry, there was an error uploading your file.";
            }
        } else if (isset($_POST['update_product']) && $_FILES['picture']['error'] == 4) {
            // No new file uploaded for update - keep existing picture
            if (isset($_POST['existing_picture'])) {
                $picture = $_POST['existing_picture'];
            }
        }
        
        if ($upload_success) {
            // Adding a new product
            if (isset($_POST['add_product'])) {
                // Check if merchant exists in the Merchant table
                $checkMerchantQuery = "SELECT merchantId FROM Merchant WHERE userId = $userId";
                $merchantResult = $conn->query($checkMerchantQuery);
                
                if ($merchantResult && $merchantResult->num_rows > 0) {
                    $merchantRow = $merchantResult->fetch_assoc();
                    $merchantId = $merchantRow['merchantId'];
                    
                    $sql = "INSERT INTO item (merchantId, itemName, picture, brand, itemPrice, quantity, description) 
                            VALUES ($merchantId, '$itemName', '$picture', '$brand', $itemPrice, $quantity, '$description')";
                    
                    if ($conn->query($sql) === TRUE) {
                        $success_message = "Product added successfully!";
                        // Redirect to avoid form resubmission
                        header("Location: /e-commerce/merchant/products.php?message=added");
                        exit();
                    } else {
                        $error_message = "Error: " . $conn->error;
                    }
                } else {
                    // Create merchant record if it doesn't exist
                    $storeName = $_SESSION['firstname'] . "'s Store"; // Default store name
                    $createMerchantQuery = "INSERT INTO Merchant (userId, storeName) VALUES ($userId, '$storeName')";
                    
                    if ($conn->query($createMerchantQuery) === TRUE) {
                        $merchantId = $conn->insert_id;
                        
                        $sql = "INSERT INTO item (merchantId, itemName, picture, brand, itemPrice, quantity, description) 
                                VALUES ($merchantId, '$itemName', '$picture', '$brand', $itemPrice, $quantity, '$description')";
                        
                        if ($conn->query($sql) === TRUE) {
                            $success_message = "Product added successfully!";
                            // Redirect to avoid form resubmission
                            header("Location: /e-commerce/merchant/products.php?message=added");
                            exit();
                        } else {
                            $error_message = "Error: " . $conn->error;
                        }
                    } else {
                        $error_message = "Error creating merchant account: " . $conn->error;
                    }
                }
            }
            // Updating existing product
            else if (isset($_POST['update_product']) && isset($_POST['itemId'])) {
                $itemId = intval($_POST['itemId']);
                
                // Get merchantId
                $merchantQuery = "SELECT merchantId FROM Merchant WHERE userId = $userId";
                $merchantResult = $conn->query($merchantQuery);
                
                if ($merchantResult && $merchantResult->num_rows > 0) {
                    $merchantRow = $merchantResult->fetch_assoc();
                    $merchantId = $merchantRow['merchantId'];
                    
                    $sql = "UPDATE item SET itemName = '$itemName', brand = '$brand', 
                            itemPrice = $itemPrice, quantity = $quantity, description = '$description'";
                    
                    // Only update picture if a new one was uploaded
                    if (!empty($picture)) {
                        $sql .= ", picture = '$picture'";
                    }
                    
                    $sql .= " WHERE itemId = $itemId AND merchantId = $merchantId";
                    
                    if ($conn->query($sql) === TRUE) {
                        $success_message = "Product updated successfully!";
                        // Redirect to avoid form resubmission
                        header("Location: /e-commerce/merchant/products.php?message=updated");
                        exit();
                    } else {
                        $error_message = "Error: " . $conn->error;
                    }
                } else {
                    $error_message = "Merchant account not found.";
                }
            }
        }
    }
    // Handle product deletion
    else if (isset($_POST['delete_product']) && isset($_POST['itemId'])) {
        $itemId = intval($_POST['itemId']);
        
        // Get merchantId
        $merchantQuery = "SELECT merchantId FROM Merchant WHERE userId = $userId";
        $merchantResult = $conn->query($merchantQuery);
        
        if ($merchantResult && $merchantResult->num_rows > 0) {
            $merchantRow = $merchantResult->fetch_assoc();
            $merchantId = $merchantRow['merchantId'];
            
            // Check if the product belongs to this merchant
            $checkSql = "SELECT * FROM item WHERE itemId = $itemId AND merchantId = $merchantId";
            $result = $conn->query($checkSql);
            
            if ($result && $result->num_rows > 0) {
                $sql = "DELETE FROM item WHERE itemId = $itemId AND merchantId = $merchantId";
                
                if ($conn->query($sql) === TRUE) {
                    $success_message = "Product deleted successfully!";
                    // Redirect to avoid form resubmission
                    header("Location: /e-commerce/merchant/products.php?message=deleted");
                    exit();
                } else {
                    $error_message = "Error: " . $conn->error;
                }
            } else {
                $error_message = "You don't have permission to delete this product.";
            }
        } else {
            $error_message = "Merchant account not found.";
        }
    }
}

// Check for success messages from redirects
if (isset($_GET['message'])) {
    if ($_GET['message'] === 'added') {
        $success_message = "Product added successfully!";
    } else if ($_GET['message'] === 'updated') {
        $success_message = "Product updated successfully!";
    } else if ($_GET['message'] === 'deleted') {
        $success_message = "Product deleted successfully!";
    }
}

// Get merchant ID for queries
$merchantId = null;
$merchantQuery = "SELECT merchantId FROM Merchant WHERE userId = $userId";
$merchantResult = $conn->query($merchantQuery);
if ($merchantResult && $merchantResult->num_rows > 0) {
    $merchantRow = $merchantResult->fetch_assoc();
    $merchantId = $merchantRow['merchantId'];
}

// Get product data for edit mode
$editProduct = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $itemId = intval($_GET['id']);
    $sql = "SELECT * FROM item WHERE itemId = $itemId";
    
    // Add merchant check if merchant ID is available
    if ($merchantId) {
        $sql .= " AND merchantId = $merchantId";
    }
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $editProduct = $result->fetch_assoc();
    } else {
        // Product not found or doesn't belong to this merchant
        header("Location: /e-commerce/merchant/products.php");
        exit();
    }
}

// Get all products for list mode
$products = [];
if ($action === 'list') {
    if ($merchantId) {
        $sql = "SELECT * FROM item WHERE merchantId = $merchantId ORDER BY itemId DESC";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
        }
    }
}

// Include header
$pageTitle = ($action === 'add') ? "Add New Product" : (($action === 'edit') ? "Edit Product" : "Manage Products");
include_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/header.php';
?>

<div class="merchant-dashboard">
    <div class="container py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/merchant/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2 mb-0">
                        <?php echo $pageTitle; ?>
                    </h1>
                    <?php if ($action === 'list'): ?>
                    <a href="/e-commerce/merchant/inventory_report.php" class="btn btn-info me-2">
                        <i class="fas fa-file-alt me-2"></i>Inventory Report
                    </a>
                    <a href="/e-commerce/merchant/products.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add New Product
                    </a>
                    <?php else: ?>
                    <a href="/e-commerce/merchant/products.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Products
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Alert Messages -->
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Add/Edit Product Form -->
                <?php if ($action === 'add' || $action === 'edit'): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="itemId" value="<?php echo $editProduct['itemId']; ?>">
                            <input type="hidden" name="existing_picture" value="<?php echo $editProduct['picture']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="itemName" class="form-label">Product Name *</label>
                                <input type="text" class="form-control" id="itemName" name="itemName" 
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($editProduct['itemName']) : ''; ?>" 
                                       required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="brand" class="form-label">Brand *</label>
                                <input type="text" class="form-control" id="brand" name="brand" 
                                       value="<?php echo $action === 'edit' ? htmlspecialchars($editProduct['brand']) : ''; ?>" 
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description <small class="text-muted">(Optional)</small></label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo $action === 'edit' ? htmlspecialchars($editProduct['description'] ?? '') : ''; ?></textarea>
                                <div class="form-text">Provide details about your product features, specifications, etc.</div>
                            </div>
                                        
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="itemPrice" class="form-label">Price (₱) *</label>
                                    <input type="number" class="form-control" id="itemPrice" name="itemPrice" 
                                           value="<?php echo $action === 'edit' ? $editProduct['itemPrice'] : ''; ?>" 
                                           min="0" step="0.01" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="quantity" class="form-label">Stock Quantity *</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" 
                                           value="<?php echo $action === 'edit' ? $editProduct['quantity'] : ''; ?>" 
                                           min="0" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="picture" class="form-label">Product Image <?php echo ($action === 'add') ? '*' : ''; ?></label>
                                <?php if ($action === 'edit' && !empty($editProduct['picture'])): ?>
                                <div class="mb-2">
                                    <img src="/e-commerce/<?php echo htmlspecialchars($editProduct['picture']); ?>" 
                                         alt="Current product image" class="img-thumbnail" style="max-height: 150px;">
                                    <p class="text-muted small">Upload a new image to replace the current one</p>
                                </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="picture" name="picture" accept="image/*" 
                                       <?php echo ($action === 'add') ? 'required' : ''; ?>>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg" name="<?php echo ($action === 'add') ? 'add_product' : 'update_product'; ?>">
                                    <?php echo ($action === 'add') ? 'Add Product' : 'Update Product'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Product List -->
                <?php else: ?>
                
                <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open text-muted mb-3" style="font-size: 3rem;"></i>
                    <h3>No products yet</h3>
                    <p class="text-muted">Start adding products to your store inventory.</p>
                    <a href="/e-commerce/merchant/products.php?action=add" class="btn btn-primary mt-2">
                        <i class="fas fa-plus me-2"></i>Add Your First Product
                    </a>
                </div>
                <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" style="width: 80px;">Image</th>
                                        <th scope="col">Product</th>
                                        <th scope="col">Brand</th>
                                        <th scope="col">Price</th>
                                        <th scope="col">Stock</th>
                                        <th scope="col" style="width: 150px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
    <?php foreach ($products as $product): ?>
    <tr>
        <td>
            <?php if (!empty($product['picture'])): ?>
            <img src="/e-commerce/<?php echo htmlspecialchars($product['picture']); ?>" 
                 alt="<?php echo htmlspecialchars($product['itemName']); ?>" 
                 class="img-thumbnail" style="max-height: 50px;">
            <?php else: ?>
            <div class="bg-light d-flex align-items-center justify-content-center" 
                 style="width: 50px; height: 50px;">
                <i class="fas fa-image text-muted"></i>
            </div>
            <?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars($product['itemName']); ?></td>
        <td><?php echo htmlspecialchars($product['brand']); ?></td>
        <td>₱<?php echo number_format($product['itemPrice'], 2); ?></td>
        <td><?php echo $product['quantity']; ?></td>
        <td>
            <div class="btn-group">
                <a href="/e-commerce/merchant/products.php?action=edit&id=<?php echo $product['itemId']; ?>" 
                   class="btn btn-sm btn-outline-primary" title="Edit">
                    <i class="fas fa-edit"></i>
                </a>
                <form method="POST" action="/e-commerce/merchant/products.php" class="d-inline">
                    <input type="hidden" name="itemId" value="<?php echo $product['itemId']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" name="delete_product" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<style>
.merchant-dashboard {
    background-color: #f5f7fb;
    min-height: calc(100vh - 80px);
}

</style>

<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/footer.php';
?>