<?php
// order/receive.php
session_start();
require_once '../includes/functions.php';
requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: history.php");
    exit();
}

$orderId = intval($_GET['id']);
$userId = $_SESSION['userId'];

$conn = getConnection();

$stmt = $conn->prepare("SELECT * FROM Orders WHERE orderId = ? AND userId = ?");
$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: history.php?error=notfound");
    exit();
}

$order = $result->fetch_assoc();

$stmt = $conn->prepare("
    SELECT o.orderId, o.orderDate, o.totalPrice, o.quantity, o.modeOfPayment, o.address,
           i.itemName, i.itemPrice, i.picture, m.storeName, 
           mu.address as storeAddress,
           u.firstname, u.lastname, u.contactNum
    FROM Orders o
    INNER JOIN Item i ON o.itemId = i.itemId
    INNER JOIN Merchant m ON o.merchantId = m.merchantId
    INNER JOIN User u ON o.userId = u.userId
    INNER JOIN User mu ON m.userId = mu.userId
    WHERE o.orderId = ? AND o.userId = ?
");

$stmt->bind_param("ii", $orderId, $userId);
$stmt->execute();
$orderDetails = $stmt->get_result()->fetch_assoc();

if (!$orderDetails) {
    header("Location: history.php?error=notfound");
    exit();
}

// Format prices for display
$orderDetails['totalPrice'] = '₱' . number_format($orderDetails['totalPrice'], 2);
$orderDetails['itemPrice'] = '₱' . number_format($orderDetails['itemPrice'], 2);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_receipt'])) {
    // Mark order as received
    $update = $conn->prepare("UPDATE Orders SET toReceive = 0, toRate = 1 WHERE orderId = ?");
    $update->bind_param("i", $orderId);
    $update->execute();
    
    // Redirect to success page or back to history
    header("Location: history.php?success=received");
    exit();
}

$title = "Confirm Receipt - Order #" . $orderId;
include_once '../includes/header.php';
?>

<!-- Include required libraries for PDF generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Confirm Order Receipt</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Please confirm that you have received your order. A receipt will be generated and downloaded automatically.
                    </div>
                    
                    <div id="receiptContent" style="display: none; background: white; padding: 30px; font-family: Arial, sans-serif;">
                        <div style="text-align: center; margin-bottom: 30px;">
                            <h2 style="color: #2c3e50; margin-bottom: 10px;">ORDER RECEIPT</h2>
                            <p style="color: #7f8c8d; margin: 0;">Powered By: Eigenman Shop!</p>
                        </div>
                        
                        <div style="border: 2px solid #3498db; padding: 20px; margin-bottom: 30px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                                <div>
                                    <strong>Order ID:</strong> #<?php echo $orderDetails['orderId']; ?>
                                </div>
                                <div>
                                    <strong>Date:</strong> <?php echo date('d M Y, g:i A', strtotime($orderDetails['orderDate'])); ?>
                                </div>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <strong>Customer:</strong> <?php echo htmlspecialchars($orderDetails['firstname'] . ' ' . $orderDetails['lastname']); ?><br>
                                <strong>Delivery Address:</strong> <?php echo htmlspecialchars($orderDetails['address']); ?>
                            </div>
                            
                            <div>
                                <strong>Store:</strong> <?php echo htmlspecialchars($orderDetails['storeName']); ?><br>
                                <strong>Store Address:</strong> <?php echo htmlspecialchars($orderDetails['storeAddress'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                            <thead>
                                <tr style="background-color: #f8f9fa;">
                                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: left;">Item</th>
                                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: center;">Quantity</th>
                                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: right;">Unit Price</th>
                                    <th style="border: 1px solid #dee2e6; padding: 12px; text-align: right;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="border: 1px solid #dee2e6; padding: 12px;">
                                        <?php echo htmlspecialchars($orderDetails['itemName']); ?>
                                    </td>
                                    <td style="border: 1px solid #dee2e6; padding: 12px; text-align: center;">
                                        <?php echo $orderDetails['quantity']; ?>
                                    </td>
                                    <td style="border: 1px solid #dee2e6; padding: 12px; text-align: right;">
                                        <?php echo $orderDetails['itemPrice']; ?>
                                    </td>
                                    <td style="border: 1px solid #dee2e6; padding: 12px; text-align: right; font-weight: bold;">
                                        <?php echo $orderDetails['totalPrice']; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div style="text-align: right; margin-bottom: 30px;">
                            <div style="display: inline-block; text-align: left;">
                                <div style="margin-bottom: 10px;">
                                    <strong>Payment Method:</strong> <?php echo htmlspecialchars($orderDetails['modeOfPayment']); ?>
                                </div>
                                <div style="font-size: 18px; font-weight: bold; color: #2c3e50;">
                                    <strong>Total Amount: <?php echo $orderDetails['totalPrice']; ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <div style="border-top: 2px solid #ecf0f1; padding-top: 20px; text-align: center; color: #7f8c8d;">
                            <p>Order received and confirmed on <?php echo date('d M Y, g:i A'); ?></p>
                            <p style="margin: 0;">Thank you for shopping with us!</p>
                        </div>
                    </div>
                    
                    <!-- Order Display for User -->
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Order Information</h6>
                            <p><strong>Order ID:</strong> #<?php echo $orderDetails['orderId']; ?></p>
                            <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($orderDetails['orderDate'])); ?></p>
                            <p><strong>Item:</strong> <?php echo htmlspecialchars($orderDetails['itemName']); ?></p>
                            <p><strong>Quantity:</strong> <?php echo $orderDetails['quantity']; ?></p>
                            <p><strong>Store:</strong> <?php echo htmlspecialchars($orderDetails['storeName']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Payment Information</h6>
                            <p><strong>Total Amount:</strong> <?php echo $orderDetails['totalPrice']; ?></p>
                            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($orderDetails['modeOfPayment']); ?></p>
                            <p><strong>Delivery Address:</strong> <?php echo htmlspecialchars($orderDetails['address']); ?></p>
                        </div>
                    </div>
                    
                    <form method="POST" class="mt-4">
                        <div class="d-flex justify-content-between">
                            <a href="history.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Back to Orders
                            </a>
                            <button type="submit" name="confirm_receipt" class="btn btn-success" id="confirmReceiptBtn">
                                <i class="bi bi-check-circle me-1"></i> Confirm Receipt & Download PDF
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmBtn = document.getElementById('confirmReceiptBtn');
    
    if(confirmBtn) {
        confirmBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent form submission temporarily
            
            // Show loading state
            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-download me-1"></i> Generating PDF...';
            btn.disabled = true;
            
            // Get the receipt content
            const element = document.getElementById('receiptContent');
            
            // Store date for filename
            const now = new Date();
            const dateStr = now.getFullYear() + '-' + 
                          String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                          String(now.getDate()).padStart(2, '0');
            
            try {
                // Make receipt visible for rendering
                element.style.display = 'block';
                element.style.position = 'absolute';
                element.style.left = '-9999px';
                element.style.top = '0';
                element.style.width = '210mm';
                element.style.backgroundColor = '#FFFFFF';
                
                // Generate PDF
                generatePDF(element).then(() => {
                    // Hide receipt content again
                    element.style.display = 'none';
                    
                    // Restore button state
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                    
                    // Now submit the form to confirm receipt
                    btn.form.submit();
                }).catch(error => {
                    console.error('PDF generation error:', error);
                    alert('There was an error generating the PDF. The order will still be confirmed.');
                    
                    // Hide receipt content
                    element.style.display = 'none';
                    
                    // Restore button state
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                    
                    // Submit form anyway
                    btn.form.submit();
                });
                
            } catch (error) {
                console.error('PDF setup error:', error);
                alert('There was an error setting up the PDF generation. The order will still be confirmed.');
                
                // Restore button state
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                
                // Submit form anyway
                btn.form.submit();
            }
            
            // Function to generate PDF
            async function generatePDF(element) {
                // Initialize jsPDF
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'mm', 'a4');
                
                // Define PDF dimensions
                const pageWidth = pdf.internal.pageSize.getWidth();
                const pageHeight = pdf.internal.pageSize.getHeight();
                const margin = 15; // mm
                
                // Use html2canvas to capture the receipt
                const canvas = await html2canvas(element, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#FFFFFF',
                    logging: false,
                    width: element.scrollWidth,
                    height: element.scrollHeight
                });
                
                // Add the canvas as image to the PDF
                const imgData = canvas.toDataURL('image/jpeg', 1.0);
                const imgWidth = pageWidth - (2 * margin);
                
                // Calculate the height of the image proportionally
                const ratio = imgWidth / canvas.width;
                const imgHeight = canvas.height * ratio;
                
                // Check if content fits on one page
                if (imgHeight <= pageHeight - (2 * margin)) {
                    // Single page
                    pdf.addImage(imgData, 'JPEG', margin, margin, imgWidth, imgHeight);
                } else {
                    // Multiple pages needed
                    const totalPages = Math.ceil(imgHeight / (pageHeight - (2 * margin)));
                    
                    for (let i = 0; i < totalPages; i++) {
                        if (i > 0) {
                            pdf.addPage();
                        }
                        
                        const sourceY = i * (pageHeight - (2 * margin)) * (canvas.height / imgHeight);
                        const sourceHeight = Math.min(
                            (pageHeight - (2 * margin)) * (canvas.height / imgHeight),
                            canvas.height - sourceY
                        );
                        
                        // Create a temporary canvas for this page
                        const tempCanvas = document.createElement('canvas');
                        const tempCtx = tempCanvas.getContext('2d');
                        tempCanvas.width = canvas.width;
                        tempCanvas.height = sourceHeight;
                        
                        tempCtx.drawImage(
                            canvas,
                            0, sourceY, canvas.width, sourceHeight,
                            0, 0, canvas.width, sourceHeight
                        );
                        
                        const tempImgData = tempCanvas.toDataURL('image/jpeg', 1.0);
                        const tempImgHeight = sourceHeight * ratio;
                        
                        pdf.addImage(tempImgData, 'JPEG', margin, margin, imgWidth, tempImgHeight);
                    }
                }
                
                // Save the PDF
                pdf.save('order-receipt-<?php echo $orderId; ?>-' + dateStr + '.pdf');
            }
        });
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>