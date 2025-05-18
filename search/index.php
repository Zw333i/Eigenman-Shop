<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/functions.php';
require_once '../config/database.php';

$search_query = '';
$search_type = 'all'; 
$search_results = [];
$error_message = '';

if (isset($_GET['query']) && !empty($_GET['query'])) {
    $search_query = trim($_GET['query']);
    
    if (isset($_GET['type']) && !empty($_GET['type'])) {
        $search_type = $_GET['type'];
    }
    
    if (isLoggedIn()) {
        $userId = $_SESSION['userId'];
        
        $stmt = $conn->prepare("INSERT INTO Search (userId, keyword, searchType) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $search_query, $search_type);
        $stmt->execute();
    }

    try {
        if ($search_type == 'item' || $search_type == 'all') {
            $item_query = "SELECT i.*, m.storeName, u.userId AS merchantUserId 
                          FROM Item i 
                          JOIN Merchant m ON i.merchantId = m.merchantId
                          JOIN User u ON m.userId = u.userId
                          WHERE i.itemName LIKE ? OR i.brand LIKE ?";
            
            $stmt = $conn->prepare($item_query);
            $search_param = "%{$search_query}%";
            $stmt->bind_param("ss", $search_param, $search_param);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $row['type'] = 'item';
                $search_results[] = $row;
            }
        }
        
        if ($search_type == 'store' || $search_type == 'all') {
            $merchant_query = "SELECT m.*, u.firstName, u.lastName, u.followers 
                              FROM Merchant m 
                              JOIN User u ON m.userId = u.userId
                              WHERE m.storeName LIKE ?";
            
            $stmt = $conn->prepare($merchant_query);
            $search_param = "%{$search_query}%";
            $stmt->bind_param("s", $search_param);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $row['type'] = 'store';
                $search_results[] = $row;
            }
        }
    } catch (Exception $e) {
        $error_message = "An error occurred while searching: " . $e->getMessage();
    }
}

$page_title = "Search Results";
include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">Search Results</h2>
            
            <form action="index.php" method="GET" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" class="form-control" name="query" placeholder="Search products or stores..." value="<?php echo htmlspecialchars($search_query); ?>" required>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="type" class="form-select">
                            <option value="all" <?php echo ($search_type == 'all') ? 'selected' : ''; ?>>All</option>
                            <option value="item" <?php echo ($search_type == 'item') ? 'selected' : ''; ?>>Items Only</option>
                            <option value="store" <?php echo ($search_type == 'store') ? 'selected' : ''; ?>>Stores Only</option>
                        </select>
                    </div>
                </div>
            </form>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($search_query)): ?>
                <div class="alert alert-info" role="alert">
                    Enter a search term to find products or stores.
                </div>
            <?php elseif (empty($search_results)): ?>
                <div class="alert alert-warning" role="alert">
                    No results found for "<?php echo htmlspecialchars($search_query); ?>".
                </div>
            <?php else: ?>
                <p>Found <?php echo count($search_results); ?> results for "<?php echo htmlspecialchars($search_query); ?>"</p>
                
                <div class="row">
                    <?php foreach ($search_results as $result): ?>
                        <?php if ($result['type'] == 'item'): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100 product-card">
                                    <div class="card-header bg-primary text-white">
                                        <span class="badge bg-secondary float-end">Item</span>
                                    </div>
                                    <?php if (!empty($result['picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($result['picture']); ?>" class="card-img-top product-img" alt="<?php echo htmlspecialchars($result['itemName']); ?>">
                                    <?php else: ?>
                                        <img src="../assets/images/product-placeholder.jpg" class="card-img-top product-img" alt="Product placeholder">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($result['itemName']); ?></h5>
                                        <p class="card-text">
                                            <strong>Brand:</strong> <?php echo htmlspecialchars($result['brand']); ?><br>
                                            <strong>Price:</strong> ₱<?php echo number_format($result['itemPrice'], 2); ?><br>
                                            <strong>Seller:</strong> <?php echo htmlspecialchars($result['storeName']); ?>
                                        </p>
                                    </div>
                                    <div class="card-footer">
                                        <a href="../product/details.php?id=<?php echo $result['itemId']; ?>" class="btn btn-primary">View Details</a>
                                        <?php if ($result['quantity'] > 0 && isLoggedIn() && $_SESSION['role'] == 'customer'): ?>
                                            <a href="../cart/add.php?id=<?php echo $result['itemId']; ?>&qty=1" class="btn btn-success">Add to Cart</a>
                                        <?php elseif ($result['quantity'] <= 0): ?>
                                            <button class="btn btn-secondary" disabled>Out of Stock</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Store Card -->
                            <div class="col-md-4 mb-4">
                                <div class="card h-100 store-card">
                                    <div class="card-header bg-secondary text-white">
                                        <span class="badge bg-info float-end">Store</span>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($result['storeName']); ?></h5>
                                        <p class="card-text">
                                            <strong>Owner:</strong> <?php echo htmlspecialchars($result['firstName'] . ' ' . $result['lastName']); ?><br>
                                            <strong>Followers:</strong> <?php echo number_format($result['followers']); ?>
                                        </p>
                                    </div>
                                    <div class="card-footer">
                                        <a href="../merchant/view.php?id=<?php echo $result['merchantId']; ?>" class="btn btn-secondary">Visit Store</a>
                                        <?php if (isLoggedIn() && $_SESSION['role'] == 'customer'): ?>
                                            <a href="../chats/index.php?merchant=<?php echo $result['merchantId']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-comment"></i> Chat
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>