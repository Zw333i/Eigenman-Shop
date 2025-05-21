<?php
// order/confirmation.php
session_start();

require_once "../config/database.php";
require_once "../includes/functions.php";

if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

$userId = $_SESSION['userId'];
$conn = getConnection();

// Get only the specific order from the orderId in session or URL
$orderId = isset($_SESSION['currentOrderId']) ? $_SESSION['currentOrderId'] : 
          (isset($_GET['orderId']) ? $_GET['orderId'] : null);

// If no orderId is available, get the latest one
if (!$orderId) {
    $sqlLatest = "SELECT orderId FROM Orders WHERE userId = ? ORDER BY orderDate DESC LIMIT 1";
    $stmtLatest = $conn->prepare($sqlLatest);
    $stmtLatest->bind_param("i", $userId);
    $stmtLatest->execute();
    $resultLatest = $stmtLatest->get_result();
    if ($row = $resultLatest->fetch_assoc()) {
        $orderId = $row['orderId'];
    }
}

if ($orderId) {
    // Get only the specific order items
    $sql = "SELECT o.orderId, o.merchantId, o.itemId, o.quantity, o.totalPrice, o.address, 
                o.contact, o.modeOfPayment, o.orderDate, o.eta, 
                i.itemName, i.brand, i.itemPrice, m.storeName
            FROM Orders o
            JOIN Item i ON o.itemId = i.itemId
            JOIN Merchant m ON o.merchantId = m.merchantId
            WHERE o.userId = ? AND o.orderId = ?
            ORDER BY o.orderDate DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $orderId);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    $totalAmount = 0;
    $orderDate = "";
    $latestOrderId = $orderId;

    while ($row = $result->fetch_assoc()) {
        if (empty($orderDate)) {
            $orderDate = $row['orderDate'];
        }
        $orders[] = $row;
        $totalAmount += $row['totalPrice'];
    }
}

// Get user information
$sqlUser = "SELECT * FROM User WHERE userId = ?";
$stmtUser = $conn->prepare($sqlUser);
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$user = $resultUser->fetch_assoc();

// Clear the session variable after use
if (isset($_SESSION['currentOrderId'])) {
    unset($_SESSION['currentOrderId']);
}

?>

<?php include_once "../includes/header.php"; ?>
<?php include_once "../includes/navbar.php"; ?>

<style>
    .order-confirmation {
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    .confirmation-icon {
        animation: pulse 2s infinite;
    }
    
    .order-details {
        border-radius: 10px;
        background-color: #f8f9fa;
        padding: 20px;
        margin-top: 30px;
    }
    
    /* Enhanced styling for receipt */
    .receipt-header {
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    
    .receipt-footer {
        border-top: 2px solid #e9ecef;
        padding-top: 15px;
        margin-top: 20px;
    }
    
    .store-logo {
        max-width: 150px;
        margin-bottom: 10px;
    }
    
    .order-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .order-table th, .order-table td {
        padding: 10px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .order-items {
        margin-top: 20px;
    }
    
    .order-item {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .order-item:last-child {
        border-bottom: none;
    }
    
    .download-btn {
        transition: all 0.3s ease;
    }
    
    .download-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    @keyframes pulse {
        0% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.05);
            opacity: 0.8;
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }
    
    .animate-fade-in {
        animation: fadeIn 1s ease-in-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* PDF-specific styles */
    @media print {
        body * {
            visibility: hidden;
        }
        .print-content, .print-content * {
            visibility: visible;
        }
        .print-content {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        .no-print {
            display: none !important;
        }
    }
</style>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card order-confirmation animate-fade-in">
                <div class="card-body text-center p-5">
                    <div class="confirmation-icon mb-4">
                        <i class="fas fa-check-circle fa-5x text-success"></i>
                    </div>
                    
                    <h2 class="mb-4">Order Placed Successfully!</h2>
                    <p class="lead">Thank you for shopping with us, <?php echo htmlspecialchars($user['firstname']); ?>!</p>
                    <p>Your order has been received and is now being processed.</p>
                    <p>You will receive order updates via notifications.</p>
                    
                    <?php if (!empty($orders)): ?>
                        <div id="receiptContent" class="order-details text-start animate-fade-in" style="animation-delay: 0.3s;">
                            <div class="print-content">
                                <div class="receipt-header text-center mb-4">
                                    <h3>Order Receipt</h3>
                                    <p class="text-muted">Order #<?php echo $latestOrderId; ?></p>
                                    <p class="text-muted"><?php echo date('F d, Y h:i A', strtotime($orderDate)); ?></p>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold">Customer Information</h6>
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></p>
                                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($orders[0]['contact']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold">Order Information</h6>
                                        <p><strong>Order ID:</strong> #<?php echo $latestOrderId; ?></p>
                                        <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($orders[0]['modeOfPayment']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h6 class="fw-bold">Delivery Information</h6>
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($orders[0]['address']); ?></p>
                                    <p><strong>Estimated Delivery:</strong> <?php echo date('F d, Y', strtotime($orders[0]['eta'])); ?></p>
                                </div>
                                
                                <div class="order-items">
                                    <h6 class="fw-bold mb-3">Items Ordered</h6>
                                    <table class="table order-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Item</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-end">Price</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($order['itemName']); ?></h6>
                                                    <p class="text-muted mb-0 small">
                                                        <?php echo htmlspecialchars($order['brand']); ?> •
                                                        Sold by <?php echo htmlspecialchars($order['storeName']); ?>
                                                    </p>
                                                </td>
                                                <td class="text-center">x<?php echo $order['quantity']; ?></td>
                                                <td class="text-end">₱<?php echo number_format($order['totalPrice'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="2" class="text-end">Total Amount:</th>
                                                <th class="text-end">₱<?php echo number_format($totalAmount, 2); ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                
                                <div class="receipt-footer mt-4 text-center">
                                    <h6 class="mb-2">Thank you for your purchase!</h6>
                                    <p class="text-muted small mb-0">For any inquiries, contact our customer support.</p>
                                    <p class="text-muted small mb-0">© <?php echo date('Y'); ?> Eigenman Shop - All rights reserved.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 animate-fade-in no-print" style="animation-delay: 0.6s;">
                            <button id="downloadPDF" class="btn btn-success download-btn">
                                <i class="fas fa-file-pdf me-2"></i>Download Receipt
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-4">
                            No order information found. Please check your order history.
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-5 animate-fade-in no-print" style="animation-delay: 0.9s;">
                        <a href="history.php" class="btn btn-primary me-2">
                            <i class="fas fa-list me-2"></i>View My Orders
                        </a>
                        <a href="../product/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add necessary libraries -->
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add animation classes with delays
    const elements = document.querySelectorAll('.animate-fade-in');
    elements.forEach((el, index) => {
        setTimeout(() => {
            el.style.opacity = '1';
        }, 300 * index);
    });

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
                pdf.save('order-receipt-<?php echo $latestOrderId; ?>-' + dateStr + '.pdf');
            }
        });
    }
});
</script>

<?php include_once "../includes/footer.php"; ?>