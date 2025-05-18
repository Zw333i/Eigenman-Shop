<?php
// product/index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$products = [];
$searchTerm = '';
$sortOption = '';
$filterBrand = '';
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 12;
$totalItems = 0;

if (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
}

if (isset($_GET['sort'])) {
    $sortOption = $_GET['sort'];
}

if (isset($_GET['brand'])) {
    $filterBrand = $_GET['brand'];
}

$query = "SELECT i.*, m.storeName FROM Item i 
          JOIN Merchant m ON i.merchantId = m.merchantId 
          WHERE 1=1";
$countQuery = "SELECT COUNT(*) as total FROM Item i 
              JOIN Merchant m ON i.merchantId = m.merchantId 
              WHERE 1=1";
$params = [];
$types = '';

if (!empty($searchTerm)) {
    $query .= " AND (i.itemName LIKE ? OR i.brand LIKE ? OR m.storeName LIKE ?)";
    $countQuery .= " AND (i.itemName LIKE ? OR i.brand LIKE ? OR m.storeName LIKE ?)";
    $searchParam = "%{$searchTerm}%";
    array_push($params, $searchParam, $searchParam, $searchParam);
    $types .= 'sss';
    
    if (isset($_SESSION['userId'])) {
        $userId = $_SESSION['userId'];
        $keyword = $searchTerm;
        $searchType = 'item';
        $stmt = $conn->prepare("INSERT INTO Search (userId, keyword, searchType) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $keyword, $searchType);
        $stmt->execute();
        $stmt->close();
    }
}

if (!empty($filterBrand)) {
    $query .= " AND i.brand = ?";
    $countQuery .= " AND i.brand = ?";
    array_push($params, $filterBrand);
    $types .= 's';
}

if (!empty($sortOption)) {
    switch ($sortOption) {
        case 'price_asc':
            $query .= " ORDER BY i.itemPrice ASC";
            break;
        case 'price_desc':
            $query .= " ORDER BY i.itemPrice DESC";
            break;
        case 'name_asc':
            $query .= " ORDER BY i.itemName ASC";
            break;
        case 'name_desc':
            $query .= " ORDER BY i.itemName DESC";
            break;
        default:
            $query .= " ORDER BY i.itemId DESC";
    }
} else {
    $query .= " ORDER BY i.itemId DESC";
}

$stmtCount = $conn->prepare($countQuery);
if (!empty($types)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$totalItems = $resultCount->fetch_assoc()['total'];
$stmtCount->close();

$totalPages = ceil($totalItems / $itemsPerPage);
$offset = ($currentPage - 1) * $itemsPerPage;

$query .= " LIMIT ?, ?";
array_push($params, $offset, $itemsPerPage);
$types .= 'ii';

$stmt = $conn->prepare($query);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

$brands = [];
$brandQuery = "SELECT DISTINCT brand FROM Item ORDER BY brand";
$brandResult = $conn->query($brandQuery);

while ($row = $brandResult->fetch_assoc()) {
    $brands[] = $row['brand'];
}

include_once '../includes/header.php';
?>

<div class="container my-5">
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search products, brands, or shops" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <select class="form-select" name="brand" onchange="this.form.submit()">
                        <option value="">All Brands</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?php echo htmlspecialchars($brand); ?>" <?php echo ($filterBrand === $brand) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brand); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <select class="form-select" name="sort" onchange="this.form.submit()">
                        <option value="" <?php echo (empty($sortOption)) ? 'selected' : ''; ?>>Sort By: Latest</option>
                        <option value="price_asc" <?php echo ($sortOption === 'price_asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo ($sortOption === 'price_desc') ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name_asc" <?php echo ($sortOption === 'name_asc') ? 'selected' : ''; ?>>Name: A to Z</option>
                        <option value="name_desc" <?php echo ($sortOption === 'name_desc') ? 'selected' : ''; ?>>Name: Z to A</option>
                    </select>
                </div>
            </form>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">
                <?php if (!empty($searchTerm)): ?>
                    Search Results for "<?php echo htmlspecialchars($searchTerm); ?>"
                <?php elseif (!empty($filterBrand)): ?>
                    <?php echo htmlspecialchars($filterBrand); ?> Products
                <?php else: ?>
                    All Products
                <?php endif; ?>
                <small class="text-muted">(<?php echo $totalItems; ?> items)</small>
            </h2>
        </div>
        
        <?php if (empty($products)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No products found. Try a different search term or filter.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100 shadow-sm product-card">
                        <a href="details.php?id=<?php echo $product['itemId']; ?>" class="text-decoration-none">
                            <?php if (!empty($product['picture'])): ?>
                                <img src="../<?php echo htmlspecialchars($product['picture']); ?>" class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['itemName']); ?>">
                            <?php else: ?>
                                <img src="../assets/images/product-placeholder.jpg" class="card-img-top product-img" alt="Product Image">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title product-title"><?php echo htmlspecialchars($product['itemName']); ?></h5>
                                <p class="card-text text-primary fw-bold">₱<?php echo number_format($product['itemPrice'], 2); ?></p>
                                <p class="card-text small text-muted">
                                    <span class="brand"><?php echo htmlspecialchars($product['brand']); ?></span> • 
                                    <span class="store"><?php echo htmlspecialchars($product['storeName']); ?></span>
                                </p>
                            </div>
                        </a>
                        <div class="card-footer bg-white d-flex justify-content-between">
                            <small class="text-muted">Stock: <?php echo $product['quantity']; ?></small>
                            <?php if (isset($_SESSION['userId']) && $_SESSION['role'] === 'customer'): ?>
                                <a href="../cart/add.php?itemId=<?php echo $product['itemId']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-cart-plus"></i> Add to Cart
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $currentPage - 1; ?><?php echo (!empty($searchTerm)) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo (!empty($sortOption)) ? '&sort=' . $sortOption : ''; ?><?php echo (!empty($filterBrand)) ? '&brand=' . urlencode($filterBrand) : ''; ?>">Previous</a>
                </li>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo ($i === $currentPage) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo (!empty($searchTerm)) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo (!empty($sortOption)) ? '&sort=' . $sortOption : ''; ?><?php echo (!empty($filterBrand)) ? '&brand=' . urlencode($filterBrand) : ''; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $currentPage + 1; ?><?php echo (!empty($searchTerm)) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo (!empty($sortOption)) ? '&sort=' . $sortOption : ''; ?><?php echo (!empty($filterBrand)) ? '&brand=' . urlencode($filterBrand) : ''; ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<style>
    .product-img {
        height: 200px;
        object-fit: cover;
    }
    
    .product-title {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .product-card {
        transition: transform 0.3s;
    }
    
    .product-card:hover {
        transform: translateY(-5px);
    }
</style>

<?php include_once '../includes/footer.php'; ?>