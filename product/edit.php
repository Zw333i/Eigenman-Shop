<?php
// product/edit.php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../auth/login.php");
    exit;
}

if ($_SESSION["role"] !== "merchant") {
    header("location: ../index.php");
    exit;
}

require_once "../config/database.php";
require_once "../includes/functions.php";

$userId = $_SESSION["userId"];
$merchantId = getMerchantIdFromUserId($conn, $userId);

if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("location: ../merchant/products.php");
    exit;
}

$itemId = $_GET["id"];

$item = null;
$sql = "SELECT * FROM Item WHERE itemId = ? AND merchantId = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ii", $itemId, $merchantId);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $item = $result->fetch_assoc();
        } else {
            header("location: ../merchant/products.php");
            exit;
        }
    }
    
    $stmt->close();
}

$updateMsg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["updateProduct"])) {
    $itemName = trim($_POST["itemName"]);
    $brand = trim($_POST["brand"]);
    $itemPrice = trim($_POST["itemPrice"]);
    $quantity = trim($_POST["quantity"]);
    
    if (empty($itemName) || empty($brand) || empty($itemPrice) || empty($quantity)) {
        $updateMsg = "Please fill in all required fields.";
    } else {
        $picture = $item["picture"]; 
        
        if (isset($_FILES["picture"]) && $_FILES["picture"]["error"] == 0) {
            $targetDir = "../assets/images/products/";
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $filename = basename($_FILES["picture"]["name"]);
            $targetFilePath = $targetDir . $userId . "_" . time() . "_" . $filename;
            $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
            
            $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
            if (in_array(strtolower($fileType), $allowTypes)) {
                if (move_uploaded_file($_FILES["picture"]["tmp_name"], $targetFilePath)) {
                    $picture = $targetFilePath;
                    
                    if ($item["picture"] != "../assets/images/product-placeholder.jpg" && file_exists($item["picture"])) {
                        unlink($item["picture"]);
                    }
                } else {
                    $updateMsg = "Sorry, there was an error uploading your file.";
                }
            } else {
                $updateMsg = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            }
        }
        
        if (empty($updateMsg)) {
            $sql = "UPDATE Item SET itemName = ?, picture = ?, brand = ?, itemPrice = ?, quantity = ? WHERE itemId = ? AND merchantId = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssddii", $itemName, $picture, $brand, $itemPrice, $quantity, $itemId, $merchantId);
                
                if ($stmt->execute()) {
                    $updateMsg = "Product updated successfully!";
                    
                    $sql = "SELECT * FROM Item WHERE itemId = ?";
                    if ($refreshStmt = $conn->prepare($sql)) {
                        $refreshStmt->bind_param("i", $itemId);
                        $refreshStmt->execute();
                        $result = $refreshStmt->get_result();
                        if ($result->num_rows == 1) {
                            $item = $result->fetch_assoc();
                        }
                        $refreshStmt->close();
                    }
                } else {
                    $updateMsg = "Oops! Something went wrong. Please try again later.";
                }
                
                $stmt->close();
            }
        }
    }
}

$conn->close();
?>

<?php include_once "../includes/header.php"; ?>
<?php include_once "../includes/navbar.php"; ?>

<div class="container mt-5 mb-5">
    <div class="row">
        <div class="col-md-3">
            <?php include_once "../merchant/sidebar.php"; ?>
        </div>
        <div class="col-md-9">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Edit Product</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($updateMsg)): ?>
                        <div class="alert <?php echo strpos($updateMsg, "successfully") !== false ? "alert-success" : "alert-danger"; ?>">
                            <?php echo $updateMsg; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $itemId); ?>" method="post" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="itemName" class="form-label">Product Name</label>
                                    <input type="text" class="form-control" id="itemName" name="itemName" value="<?php echo htmlspecialchars($item["itemName"]); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="brand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" id="brand" name="brand" value="<?php echo htmlspecialchars($item["brand"]); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="itemPrice" class="form-label">Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" step="0.01" min="0" class="form-control" id="itemPrice" name="itemPrice" value="<?php echo htmlspecialchars($item["itemPrice"]); ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Quantity in Stock</label>
                                    <input type="number" min="0" class="form-control" id="quantity" name="quantity" value="<?php echo htmlspecialchars($item["quantity"]); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="picture" class="form-label">Product Image</label>
                                    <div class="card mb-2">
                                        <img src="<?php echo htmlspecialchars($item["picture"]); ?>" alt="<?php echo htmlspecialchars($item["itemName"]); ?>" class="card-img-top" style="height: 200px; object-fit: contain;">
                                    </div>
                                    <input type="file" class="form-control" id="picture" name="picture">
                                    <div class="form-text">Leave empty to keep the current image.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="../merchant/products.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Products
                            </a>
                            <button type="submit" name="updateProduct" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once "../includes/footer.php"; ?>