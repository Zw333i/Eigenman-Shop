<?php
// merchant/inventory_report.php
session_start();

// Check if user is logged in and is a merchant
if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'merchant') {
    header("Location: /e-commerce/auth/login.php");
    exit();
}

// Include database connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/config/database.php';

$userId = $_SESSION['userId'];

// Get merchant ID for queries
$merchantId = null;
$merchantQuery = "SELECT merchantId, storeName FROM Merchant WHERE userId = $userId";
$merchantResult = $conn->query($merchantQuery);
if ($merchantResult && $merchantResult->num_rows > 0) {
    $merchantRow = $merchantResult->fetch_assoc();
    $merchantId = $merchantRow['merchantId'];
    $storeName = $merchantRow['storeName'];
}

// Get all products for report
$products = [];
if ($merchantId) {
    $sql = "SELECT * FROM item WHERE merchantId = $merchantId ORDER BY itemName ASC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
}

// Calculate summary data
$totalProducts = count($products);
$totalStock = 0;
$totalValue = 0;
$lowStockCount = 0;
$outOfStockCount = 0;

foreach ($products as $product) {
    $totalStock += $product['quantity'];
    $totalValue += ($product['quantity'] * $product['itemPrice']);
    
    if ($product['quantity'] == 0) {
        $outOfStockCount++;
    } else if ($product['quantity'] <= 5) { // Consider 5 or fewer as low stock
        $lowStockCount++;
    }
}

// Include header
$pageTitle = "Inventory Report";
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
                        Inventory Report
                    </h1>
                    <div>
                        <button id="downloadPDF" class="btn btn-success">
                            <i class="fas fa-download me-2"></i>Download PDF Report
                        </button>
                        <a href="/e-commerce/merchant/products.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-arrow-left me-2"></i>Back to Products
                        </a>
                    </div>
                </div>
                
                <!-- Report Content -->
                <div id="receiptContent" class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title mb-0"><?php echo htmlspecialchars($storeName); ?> - Inventory Report</h3>
                        <p class="mb-0 mt-1">Generated on: <?php echo date('F j, Y, g:i a'); ?></p>
                    </div>
                    <div class="card-body">
                        <!-- Summary Section -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-light h-100">
                                    <div class="card-body text-center">
                                        <h4 class="h5">Total Products</h4>
                                        <p class="display-4 mb-0"><?php echo $totalProducts; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light h-100">
                                    <div class="card-body text-center">
                                        <h4 class="h5">Total Stock</h4>
                                        <p class="display-4 mb-0"><?php echo $totalStock; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light h-100">
                                    <div class="card-body text-center">
                                        <h4 class="h5">Low Stock Items</h4>
                                        <p class="display-4 mb-0"><?php echo $lowStockCount; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light h-100">
                                    <div class="card-body text-center">
                                        <h4 class="h5">Out of Stock</h4>
                                        <p class="display-4 mb-0"><?php echo $outOfStockCount; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h4 class="mb-3">Inventory Value: ₱<?php echo number_format($totalValue, 2); ?></h4>
                        
                        <!-- Product List -->
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Product ID</th>
                                        <th>Name</th>
                                        <th>Brand</th>
                                        <th>Price (₱)</th>
                                        <th>Quantity</th>
                                        <th>Value (₱)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-3">No products found in inventory</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?php echo $product['itemId']; ?></td>
                                            <td><?php echo htmlspecialchars($product['itemName']); ?></td>
                                            <td><?php echo htmlspecialchars($product['brand']); ?></td>
                                            <td><?php echo number_format($product['itemPrice'], 2); ?></td>
                                            <td><?php echo $product['quantity']; ?></td>
                                            <td><?php echo number_format($product['quantity'] * $product['itemPrice'], 2); ?></td>
                                            <td>
                                                <?php if ($product['quantity'] == 0): ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                                <?php elseif ($product['quantity'] <= 5): ?>
                                                <span class="badge bg-warning text-dark">Low Stock</span>
                                                <?php else: ?>
                                                <span class="badge bg-success">In Stock</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <p class="text-muted mb-0"><small>This report provides a snapshot of your inventory as of <?php echo date('Y-m-d H:i:s'); ?></small></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include necessary libraries for PDF generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // PDF Download functionality
    const downloadBtn = document.getElementById('downloadPDF');
    if(downloadBtn) {
        downloadBtn.addEventListener('click', function() {
            // Show loading state
            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating PDF...';
            btn.disabled = true;
            
            // Get the receipt content
            const element = document.getElementById('receiptContent');
            
            // Store date for filename
            const now = new Date();
            const dateStr = now.getFullYear() + '-' + 
                          String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                          String(now.getDate()).padStart(2, '0');
            
            try {
                // Create a clone to avoid affecting the displayed content
                const clone = element.cloneNode(true);
                clone.style.background = '#FFFFFF';
                clone.style.width = '210mm'; // A4 width
                clone.style.padding = '15mm';
                clone.style.position = 'absolute';
                clone.style.left = '-9999px';
                document.body.appendChild(clone);
                
                // Handle pagination using html2canvas and jsPDF
                generatePDF(clone).then(() => {
                    // Cleanup
                    document.body.removeChild(clone);
                    
                    // Restore button state
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }).catch(error => {
                    console.error('PDF generation error:', error);
                    alert('There was an error generating the PDF. Please try again.');
                    
                    // Cleanup
                    if (document.body.contains(clone)) {
                        document.body.removeChild(clone);
                    }
                    
                    // Restore button state
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                });
                
            } catch (error) {
                console.error('PDF setup error:', error);
                alert('There was an error setting up the PDF generation. Please try again.');
                
                // Restore button state
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
            
            // Function to generate a multi-page PDF
            async function generatePDF(element) {
                // Initialize jsPDF
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                
                // Define PDF dimensions
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                const margin = 10; // mm
                const usableHeight = pageHeight - (2 * margin);
                
                // Get element total height
                const totalHeight = element.scrollHeight;
                
                // Calculate number of pages needed
                const totalPages = Math.ceil(totalHeight / (usableHeight * 3.779)); // Convert mm to px
                
                // Page by page rendering
                for (let i = 0; i < totalPages; i++) {
                    // Add new page after the first page
                    if (i > 0) {
                        pdf.addPage();
                    }
                    
                    // Calculate the vertical position to capture for this page
                    const captureHeight = Math.min(element.scrollHeight, usableHeight * 3.779); // Approx px-to-mm conversion
                    const captureY = i * usableHeight * 3.779;
                    
                    // Use html2canvas to capture the specific section
                    const canvas = await html2canvas(element, {
                        scale: 2,
                        useCORS: true,
                        allowTaint: true,
                        backgroundColor: '#FFFFFF',
                        logging: false,
                        windowWidth: element.scrollWidth,
                        windowHeight: totalHeight,
                        y: captureY,
                        height: captureHeight
                    });
                    
                    // Add the canvas as image to the PDF
                    const imgData = canvas.toDataURL('image/jpeg', 1.0);
                    const imgWidth = pageWidth - (2 * margin);
                    
                    // Calculate the height of the image proportionally
                    const ratio = imgWidth / canvas.width;
                    const imgHeight = canvas.height * ratio;
                    
                    // Add image to PDF
                    pdf.addImage(imgData, 'JPEG', margin, margin, imgWidth, imgHeight);
                }
                
                // Save the PDF
                const storeName = '<?php echo str_replace("'", "\\'", $storeName); ?>';
                pdf.save('inventory-report-' + storeName + '-' + dateStr + '.pdf');
            }
        });
    }
});
</script>

<style>
.merchant-dashboard {
    background-color: #f5f7fb;
    min-height: calc(100vh - 80px);
}

/* Print styling - hide unnecessary elements when printing */
@media print {
    .sidebar, .btn, .no-print {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/footer.php';
?>