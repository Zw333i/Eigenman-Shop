<?php
session_start();
require_once '../includes/functions.php';
requireLogin();

//order/process_payment.php
if (isset($_POST['action']) && $_POST['action'] == 'process_payment' && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    $user_id = $_SESSION['userId'];
    
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT o.*, i.itemName, i.itemPrice, i.brand, m.storeName 
                           FROM Orders o
                           JOIN Item i ON o.itemId = i.itemId
                           JOIN Merchant m ON i.merchantId = m.merchantId
                           WHERE o.orderId = ? AND o.userId = ? AND o.toPay = 1");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        
        // Update order status
        $updateStmt = $conn->prepare("UPDATE Orders SET toPay = 0, toShip = 1, paymentDate = NOW() WHERE orderId = ?");
        $updateStmt->bind_param("i", $order_id);
        
        if ($updateStmt->execute()) {
            // Set success message
            $_SESSION['payment_success'] = true;
            
            // Fetch user information for receipt
            $userStmt = $conn->prepare("SELECT * FROM User WHERE userId = ?");
            $userStmt->bind_param("i", $user_id);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            $user = $userResult->fetch_assoc();
            $userStmt->close();
            
            // Display the receipt page
            generateReceiptPage($order, $user);
            exit;
        } else {
            // Set error message
            $_SESSION['payment_error'] = "Error processing payment. Please try again.";
        }
        
        $updateStmt->close();
    } else {
        // Set error message
        $_SESSION['payment_error'] = "Invalid order or payment already processed.";
    }
    
    $stmt->close();
    $conn->close();
    
    // Redirect back to order details page
    header("Location: details.php?id=" . $order_id);
    exit;
} else {
    // Redirect to order history if no valid action
    header("Location: history.php");
    exit;
}

function generateReceiptPage($order, $user) {
    // Format the price with PHP currency symbol
    function formatPHP($price) {
        return '₱' . number_format($price, 2);
    }
    
    // Calculate shipping fee
    $shippingFee = $order['totalPrice'] - ($order['itemPrice'] * $order['quantity']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Include jsPDF and html2canvas libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        :root {
            --primary: #4361ee;
            --success: #4cc9f0;
            --error: #f72585;
            --text: #2b2d42;
            --light: #f8f9fa;
            --white: #ffffff;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .confirmation-container {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 600px;
            overflow: hidden;
            transform: translateY(20px);
            opacity: 0;
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        .confirmation-header {
            background: var(--primary);
            color: var(--white);
            padding: 24px;
            text-align: center;
            position: relative;
        }
        
        .confirmation-icon {
            width: 80px;
            height: 80px;
            background: var(--white);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
            animation: bounceIn 0.8s ease-out;
        }
        
        .confirmation-icon svg {
            width: 40px;
            height: 40px;
            color: var(--primary);
        }
        
        .confirmation-body {
            padding: 32px;
            text-align: center;
        }
        
        .confirmation-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        
        .confirmation-message {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 24px;
            color: #6c757d;
        }
        
        #receiptContent {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: left;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #ccc;
        }
        
        .receipt-logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .detail-row.total {
            font-weight: bold;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
        }
        
        .detail-label {
            font-weight: 500;
        }
        
        .receipt-footer {
            text-align: center;
            font-size: 14px;
            color: #6c757d;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
        }
        
        .confirmation-actions {
            display: flex;
            gap: 16px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            flex: 1;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-primary:hover {
            background: #3a56d4;
            transform: translateY(-2px);
        }
        
        .btn-outline:hover {
            background: rgba(67, 97, 238, 0.1);
            transform: translateY(-2px);
        }
        
        .progress-bar {
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
            margin-top: 20px;
            overflow: hidden;
            border-radius: 2px;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--white);
            width: 0;
            animation: progress 2s ease-in-out forwards;
        }
        
        .animate-fade-in {
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes bounceIn {
            0% {
                transform: scale(0.5);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
                opacity: 1;
            }
            100% {
                transform: scale(1);
            }
        }
        
        @keyframes progress {
            from {
                width: 0;
            }
            to {
                width: 100%;
            }
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }
        
        .pulse {
            animation: pulse 1.5s infinite;
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .confirmation-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-header">
            <div class="confirmation-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h1>Payment Successful</h1>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>
        <div class="confirmation-body">
            <h2 class="confirmation-title">Thank you for your purchase!</h2>
            <p class="confirmation-message">
                Your payment has been processed successfully. We've sent a confirmation email to your registered address.
                Your order will be prepared for shipment shortly.
            </p>
            
            <!-- Receipt Content Container (This will be used for PDF generation) -->
            <div id="receiptContent" class="animate-fade-in">
                <div class="receipt-header">
                    <div class="receipt-logo">Eigenman</div>
                    <div>Official Payment Receipt</div>
                </div>
                
                <div class="receipt-details">
                    <div class="detail-row">
                        <span class="detail-label">Receipt #:</span>
                        <span>Eigenman#<?php echo date('Ymd') . '-' . $order['orderId']; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Order ID:</span>
                        <span>#<?php echo htmlspecialchars($order['orderId']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Date:</span>
                        <span><?php echo date('F j, Y'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Method:</span>
                        <span><?php echo ucfirst(htmlspecialchars($order['modeOfPayment'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Customer:</span>
                        <span><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></span>
                    </div>
                </div>
                
                <hr class="my-3">
                
                <div class="item-details">
                    <h6 class="mb-3">Item Details</h6>
                    <div class="detail-row">
                        <span class="detail-label">Item:</span>
                        <span><?php echo htmlspecialchars($order['itemName']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Brand:</span>
                        <span><?php echo htmlspecialchars($order['brand']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Store:</span>
                        <span><?php echo htmlspecialchars($order['storeName']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Quantity:</span>
                        <span><?php echo $order['quantity']; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Unit Price:</span>
                        <span><?php echo formatPHP($order['itemPrice']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Subtotal:</span>
                        <span><?php echo formatPHP($order['itemPrice'] * $order['quantity']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Shipping Fee:</span>
                        <span><?php echo formatPHP($shippingFee >= 0 ? $shippingFee : 0); ?></span>
                    </div>
                    
                    <div class="detail-row total">
                        <span class="detail-label">Total Amount:</span>
                        <span><?php echo formatPHP($order['totalPrice']); ?></span>
                    </div>
                </div>
                
                <div class="receipt-footer">
                    <div>Thank you for shopping with Eigenman!</div>
                    <div>For any inquiries, please contact support@eigenman.com</div>
                </div>
            </div>
            
            <div class="confirmation-actions">
                <button id="downloadPDF" class="btn btn-primary">
                    <i class="bi bi-download me-2"></i>Download Receipt PDF
                </button>
                <a href="details.php?id=<?php echo $order['orderId']; ?>" class="btn btn-outline">
                    <i class="bi bi-eye me-2"></i>View Order Details
                </a>
            </div>
        </div>
    </div>

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
                btn.innerHTML = '<i class="bi bi-spinner spin me-2"></i>Generating PDF...';
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
                    
                    // Save the PDF with an appropriate filename
                    pdf.save('payment-receipt-<?php echo $order['orderId']; ?>-' + dateStr + '.pdf');
                }
            });
        }
    });
    </script>
</body>
</html>
<?php
}
?>