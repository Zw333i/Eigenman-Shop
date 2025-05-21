<?php
// merchant/sales_report.php

session_start();

if (!isset($_SESSION['userId']) || $_SESSION['role'] !== 'merchant') {
    header("Location: /e-commerce/auth/login.php");
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/config/database.php';

$userId = $_SESSION['userId'];
$timeFrame = isset($_GET['timeFrame']) ? $_GET['timeFrame'] : 'monthly';
$dateFormat = 'Y-m-d';
$groupByFormat = '%Y-%m-%d';
$chartLabelFormat = 'M d';

// Set time frame options
switch ($timeFrame) {
    case 'daily':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');
        $dateFormat = 'Y-m-d';
        $groupByFormat = '%Y-%m-%d';
        $chartLabelFormat = 'M d';
        $timeFrameTitle = "Daily Sales (Last 7 Days)";
        break;
    case 'weekly':
        $startDate = date('Y-m-d', strtotime('-12 weeks'));
        $endDate = date('Y-m-d');
        $dateFormat = 'Y-m-d';
        $groupByFormat = '%Y-%U';
        $chartLabelFormat = 'Week %U';
        $timeFrameTitle = "Weekly Sales (Last 12 Weeks)";
        break;
    case 'yearly':
        $startDate = date('Y-m-d', strtotime('-3 years'));
        $endDate = date('Y-m-d');
        $dateFormat = 'Y';
        $groupByFormat = '%Y';
        $chartLabelFormat = 'Y';
        $timeFrameTitle = "Yearly Sales (Last 3 Years)";
        break;
    case 'monthly':
    default:
        $startDate = date('Y-m-d', strtotime('-12 months'));
        $endDate = date('Y-m-d');
        $dateFormat = 'Y-m';
        $groupByFormat = '%Y-%m';
        $chartLabelFormat = 'M Y';
        $timeFrameTitle = "Monthly Sales (Last 12 Months)";
        break;
}

// Get aggregated sales data for chart
$chartQuery = "SELECT 
                DATE_FORMAT(orderDate, '$groupByFormat') as period,
                SUM(totalPrice) as total,
                COUNT(*) as orderCount
               FROM Orders 
               WHERE merchantId = $userId 
               AND orderDate BETWEEN '$startDate' AND '$endDate' 
               GROUP BY period 
               ORDER BY period ASC";
$chartResult = $conn->query($chartQuery);
$chartData = [];
$chartLabels = [];
$chartValues = [];
$totalSales = 0;
$totalOrders = 0;

if ($chartResult && $chartResult->num_rows > 0) {
    while ($row = $chartResult->fetch_assoc()) {
        $chartData[] = $row;
        $chartLabels[] = ($timeFrame === 'weekly') ? 
            'Week ' . date('W', strtotime(str_replace('-', '', $row['period']) . '01')) : 
            date($chartLabelFormat, strtotime($row['period'] . ($timeFrame === 'monthly' ? '-01' : '')));
        $chartValues[] = $row['total'];
        $totalSales += $row['total'];
        $totalOrders += $row['orderCount'];
    }
}

// Get detailed sales data
$ordersQuery = "SELECT 
                o.orderId, 
                o.orderDate, 
                o.totalPrice, 
                o.quantity,
                CONCAT(u.firstname, ' ', u.lastname) as customerName,
                i.itemName,
                CASE
                    WHEN o.toShip = 1 THEN 'To Ship'
                    WHEN o.toReceive = 1 THEN 'Shipped'
                    WHEN o.toRate = 1 THEN 'Delivered'
                    ELSE 'Completed'
                END as status
                FROM Orders o
                INNER JOIN User u ON o.userId = u.userId
                INNER JOIN Item i ON o.itemId = i.itemId
                WHERE o.merchantId = $userId 
                AND o.orderDate BETWEEN '$startDate' AND '$endDate'
                ORDER BY o.orderDate DESC";
$ordersResult = $conn->query($ordersQuery);

// Get top products
$topProductsQuery = "SELECT 
                    i.itemId, 
                    i.itemName, 
                    SUM(o.quantity) as totalQuantity,
                    SUM(o.totalPrice) as totalRevenue,
                    COUNT(o.orderId) as orderCount
                    FROM Orders o 
                    JOIN Item i ON o.itemId = i.itemId
                    WHERE o.merchantId = $userId 
                    AND o.orderDate BETWEEN '$startDate' AND '$endDate'
                    GROUP BY i.itemId 
                    ORDER BY totalRevenue DESC 
                    LIMIT 5";
$topProductsResult = $conn->query($topProductsQuery);

// Get top customers
$topCustomersQuery = "SELECT 
                     u.userId,
                     CONCAT(u.firstname, ' ', u.lastname) as customerName,
                     COUNT(o.orderId) as orderCount,
                     SUM(o.totalPrice) as totalSpent
                     FROM Orders o 
                     JOIN User u ON o.userId = u.userId
                     WHERE o.merchantId = $userId 
                     AND o.orderDate BETWEEN '$startDate' AND '$endDate'
                     GROUP BY u.userId 
                     ORDER BY totalSpent DESC 
                     LIMIT 5";
$topCustomersResult = $conn->query($topCustomersQuery);

// Get average order value
$avgOrderQuery = "SELECT AVG(totalPrice) as avgOrder FROM Orders WHERE merchantId = $userId AND orderDate BETWEEN '$startDate' AND '$endDate'";
$avgOrderResult = $conn->query($avgOrderQuery);
$avgOrderValue = 0;

if ($avgOrderResult && $avgOrderResult->num_rows > 0) {
    $row = $avgOrderResult->fetch_assoc();
    $avgOrderValue = $row['avgOrder'] ?: 0;
}

// Calculate average daily sales
$totalDays = max(1, floor((strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24)));
$avgDailySales = $totalSales / $totalDays;

$pageTitle = "Sales Report";
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="mb-0">Sales Report</h1>
                        <p class="text-muted"><?php echo $timeFrameTitle; ?></p>
                    </div>
                    <div>
                        <button id="downloadPDF" class="btn btn-success me-2">
                            <i class="fas fa-download me-2"></i>Download PDF
                        </button>
                        <div class="btn-group">
                            <a href="?timeFrame=daily" class="btn btn-outline-primary <?php echo $timeFrame === 'daily' ? 'active' : ''; ?>">Daily</a>
                            <a href="?timeFrame=weekly" class="btn btn-outline-primary <?php echo $timeFrame === 'weekly' ? 'active' : ''; ?>">Weekly</a>
                            <a href="?timeFrame=monthly" class="btn btn-outline-primary <?php echo $timeFrame === 'monthly' ? 'active' : ''; ?>">Monthly</a>
                            <a href="?timeFrame=yearly" class="btn btn-outline-primary <?php echo $timeFrame === 'yearly' ? 'active' : ''; ?>">Yearly</a>
                        </div>
                    </div>
                </div>
                
                <!-- Report to be downloaded -->
                <div id="reportContent">
                    <!-- Summary Stats -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-peso-sign text-success mb-2" style="font-size: 2rem;"></i>
                                    <h5 class="stat-value">₱<?php echo number_format($totalSales, 2); ?></h5>
                                    <p class="stat-label">Total Sales</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-shopping-bag text-primary mb-2" style="font-size: 2rem;"></i>
                                    <h5 class="stat-value"><?php echo $totalOrders; ?></h5>
                                    <p class="stat-label">Total Orders</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-receipt text-info mb-2" style="font-size: 2rem;"></i>
                                    <h5 class="stat-value">₱<?php echo number_format($avgOrderValue, 2); ?></h5>
                                    <p class="stat-label">Avg. Order Value</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line text-warning mb-2" style="font-size: 2rem;"></i>
                                    <h5 class="stat-value">₱<?php echo number_format($avgDailySales, 2); ?></h5>
                                    <p class="stat-label">Avg. Daily Sales</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sales Chart -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Sales Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" height="300"></canvas>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <!-- Top Products -->
                        <div class="col-md-6 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Top Products</h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php if ($topProductsResult && $topProductsResult->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Orders</th>
                                                    <th>Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($product = $topProductsResult->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($product['itemName']); ?></td>
                                                    <td><?php echo $product['orderCount']; ?></td>
                                                    <td>₱<?php echo number_format($product['totalRevenue'], 2); ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-4">
                                        <p class="mb-0">No product data available</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Top Customers -->
                        <div class="col-md-6 mb-4">
                            <div class="card shadow-sm h-100">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">Top Customers</h5>
                                </div>
                                <div class="card-body p-0">
                                    <?php if ($topCustomersResult && $topCustomersResult->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Customer</th>
                                                    <th>Orders</th>
                                                    <th>Total Spent</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($customer = $topCustomersResult->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($customer['customerName']); ?></td>
                                                    <td><?php echo $customer['orderCount']; ?></td>
                                                    <td>₱<?php echo number_format($customer['totalSpent'], 2); ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-4">
                                        <p class="mb-0">No customer data available</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Details -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Order Details</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($ordersResult && $ordersResult->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Date</th>
                                            <th>Customer</th>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($order = $ordersResult->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?php echo $order['orderId']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($order['orderDate'])); ?></td>
                                            <td><?php echo htmlspecialchars($order['customerName']); ?></td>
                                            <td><?php echo htmlspecialchars($order['itemName']); ?></td>
                                            <td><?php echo $order['quantity']; ?></td>
                                            <td>
                                                <?php 
                                                $statusClass = '';
                                                switch($order['status']) {
                                                    case 'To Ship':
                                                        $statusClass = 'bg-warning';
                                                        break;
                                                    case 'Shipped':
                                                        $statusClass = 'bg-info';
                                                        break;
                                                    case 'Delivered':
                                                        $statusClass = 'bg-success';
                                                        break;
                                                    default:
                                                        $statusClass = 'bg-secondary';
                                                }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>"><?php echo $order['status']; ?></span>
                                            </td>
                                            <td>₱<?php echo number_format($order['totalPrice'], 2); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-bag text-muted mb-3" style="font-size: 3rem;"></i>
                                <h5>No orders in this period</h5>
                                <p class="text-muted">Try changing the time frame to view more data.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.merchant-dashboard {
    background-color: #f5f7fb;
    min-height: calc(100vh - 80px);
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 600;
    margin: 10px 0 5px;
}

.stat-label {
    font-size: 0.9rem;
    color: #6c757d;
    margin: 0;
}

.table th, .table td {
    padding: 0.75rem;
    vertical-align: middle;
}

.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

@media print {
    .sidebar, .btn-group, .merchant-dashboard {
        display: none !important;
    }
    
    #reportContent {
        display: block !important;
        width: 100% !important;
    }
}
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<!-- html2pdf.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ensure Chart is loaded
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded');
        return;
    }

    // Chart.js initialization
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Sales (₱)',
                data: <?php echo json_encode($chartValues); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Sales: ₱' + context.raw.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // PDF Download functionality
    document.getElementById('downloadPDF').addEventListener('click', function() {
        try {
            // Store canvas image data
            const canvas = document.getElementById('salesChart');
            const canvasImg = canvas.toDataURL('image/png');
            
            // Replace canvas with img for PDF
            const canvasContainer = canvas.parentNode;
            const img = document.createElement('img');
            img.src = canvasImg;
            img.style.width = '100%';
            img.style.maxHeight = '300px';
            canvas.style.display = 'none';
            canvasContainer.appendChild(img);
            
            // Store date for filename
            const now = new Date();
            const dateStr = now.getFullYear() + '-' + 
                          String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                          String(now.getDate()).padStart(2, '0');
            
            // Generate PDF
            const element = document.getElementById('reportContent');
            const opt = {
                margin: 10,
                filename: 'sales-report-' + dateStr + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save().then(function() {
                // Restore canvas after PDF generation
                img.remove();
                canvas.style.display = 'block';
            }).catch(function(error) {
                console.error('PDF generation error:', error);
                alert('There was an error generating the PDF. Please try again.');
                img.remove();
                canvas.style.display = 'block';
            });
        } catch (error) {
            console.error('PDF generation error:', error);
            alert('There was an error generating the PDF. Please try again.');
        }
    });
});
</script>

<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/footer.php';
?>