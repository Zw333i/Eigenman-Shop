<?php
session_start();
require_once '../includes/functions.php';
requireLogin();

// Process the payment
if (isset($_POST['action']) && $_POST['action'] == 'process_payment' && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    $user_id = $_SESSION['userId'];
    
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM Orders WHERE orderId = ? AND userId = ? AND toPay = 1");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update order status
        $updateStmt = $conn->prepare("UPDATE Orders SET toPay = 0, toShip = 1 WHERE orderId = ?");
        $updateStmt->bind_param("i", $order_id);
        
        if ($updateStmt->execute()) {
            // Set success message
            $_SESSION['payment_success'] = true;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            max-width: 500px;
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
        
        .confirmation-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
        }
        
        .detail-label {
            font-weight: 500;
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
            
            <div class="confirmation-details">
                <div class="detail-row">
                    <span class="detail-label">Order ID:</span>
                    <span>#<?php echo htmlspecialchars($order_id); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span><?php echo date('F j, Y'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span>Payment Confirmed</span>
                </div>
            </div>
            
            <div class="confirmation-actions">
                <a href="details.php?id=<?php echo $order_id; ?>" class="btn btn-primary pulse">View Order Details</a>
                <a href="history.php" class="btn btn-outline">Back to Orders</a>
            </div>
        </div>
    </div>
</body>
</html>