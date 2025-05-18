<?php
session_start();
require_once '../includes/functions.php';

// order/rate.php
requireLogin();

$title = "Rate Product";
$user = getCurrentUser();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: history.php");
    exit;
}

$order_id = $_GET['id'];

if (!isset($_SESSION['userId'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['userId'];

$conn = getConnection();

$stmt = $conn->prepare("SELECT o.*, i.itemName, i.picture, i.brand, m.storeName 
                       FROM Orders o
                       JOIN Item i ON o.itemId = i.itemId
                       JOIN Merchant m ON i.merchantId = m.merchantId
                       WHERE o.orderId = ? AND o.userId = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: history.php");
    exit;
}

$order = $result->fetch_assoc();

if (!$order['toRate']) {
    header("Location: details.php?id=" . $order_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['rating']) && is_numeric($_POST['rating']) && $_POST['rating'] >= 1 && $_POST['rating'] <= 5) {
        $rating = $_POST['rating'];
        $review = isset($_POST['review']) ? $_POST['review'] : '';
        
        $stmt = $conn->prepare("UPDATE Orders SET rating = ?, review = ?, toRate = 0, ratingDate = NOW() WHERE orderId = ?");
        $stmt->bind_param("isi", $rating, $review, $order_id);
        
        if ($stmt->execute()) {
            $merchant_id = $order['merchantId'];
            $message = "User " . htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) . " rated your product with " . $rating . " stars!";
            
            $stmt = $conn->prepare("INSERT INTO Notifications (receiverId, senderId, orderId, message, timestamp) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiis", $merchant_id, $user_id, $order_id, $message);
            $stmt->execute();
            
            $_SESSION['success_message'] = "Thank you for rating this product!";
            header("Location: details.php?id=" . $order_id);
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to submit your rating. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "Please select a valid rating (1-5 stars).";
    }
}

include_once '../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php" class="text-decoration-none text-primary">Home</a></li>
                    <li class="breadcrumb-item"><a href="history.php" class="text-decoration-none text-primary">Order History</a></li>
                    <li class="breadcrumb-item"><a href="details.php?id=<?php echo $order_id; ?>" class="text-decoration-none text-primary">Order #<?php echo $order_id; ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Rate Product</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Rating Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3" style="border-bottom: 2px solid #0d6efd;">
                    <h5 class="mb-0 text-primary">Rate Your Purchase</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <?php if (!empty($order['picture'])): ?>
                                <img src="../<?php echo htmlspecialchars($order['picture']); ?>" 
                                     alt="<?php echo htmlspecialchars($order['itemName']); ?>" 
                                     class="img-fluid rounded shadow-sm" style="max-height: 150px; width: auto;">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center rounded" style="height: 150px; width: 150px;">
                                    <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <h5 class="mb-1"><?php echo htmlspecialchars($order['itemName']); ?></h5>
                            <p class="mb-1 text-muted">Brand: <?php echo htmlspecialchars($order['brand']); ?></p>
                            <p class="mb-1 text-muted">Store: <?php echo htmlspecialchars($order['storeName']); ?></p>
                            <p class="mb-0 text-muted small">
                                <i class="bi bi-calendar-check me-1"></i>
                                Purchased on: <?php echo date('M d, Y', strtotime($order['orderDate'])); ?>
                            </p>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <form action="" method="post">
                        <div class="mb-4 text-center">
                            <h6 class="mb-3">How would you rate this product?</h6>
                            <div class="rating-container">
                                <div class="rating">
                                    <input type="radio" id="star-5" name="rating" value="5" required>
                                    <label for="star-5">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path pathLength="360" d="M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.62L12,2L9.19,8.62L2,9.24L7.45,13.97L5.82,21L12,17.27Z"></path></svg>
                                    </label>
                                    <input type="radio" id="star-4" name="rating" value="4">
                                    <label for="star-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path pathLength="360" d="M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.62L12,2L9.19,8.62L2,9.24L7.45,13.97L5.82,21L12,17.27Z"></path></svg>
                                    </label>
                                    <input type="radio" id="star-3" name="rating" value="3">
                                    <label for="star-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path pathLength="360" d="M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.62L12,2L9.19,8.62L2,9.24L7.45,13.97L5.82,21L12,17.27Z"></path></svg>
                                    </label>
                                    <input type="radio" id="star-2" name="rating" value="2">
                                    <label for="star-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path pathLength="360" d="M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.62L12,2L9.19,8.62L2,9.24L7.45,13.97L5.82,21L12,17.27Z"></path></svg>
                                    </label>
                                    <input type="radio" id="star-1" name="rating" value="1">
                                    <label for="star-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path pathLength="360" d="M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.62L12,2L9.19,8.62L2,9.24L7.45,13.97L5.82,21L12,17.27Z"></path></svg>
                                    </label>
                                </div>
                                <div class="rating-hint mt-2">
                                    <span id="rating-text" class="small text-muted">Select a rating</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="review" class="form-label">Share your thoughts (optional)</label>
                            <textarea class="form-control" id="review" name="review" rows="4" placeholder="What did you like or dislike about this product?"></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="details.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Back
                            </a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-star-fill me-1"></i> Submit Rating
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rating {
  display: flex;
  flex-direction: row-reverse;
  gap: 0.3rem;
  --stroke: #666;
  --fill: #ffc73a;
  justify-content: center;
  margin: 0 auto;
}
.rating input {
  appearance: unset;
}
.rating label {
  cursor: pointer;
}
.rating svg {
  width: 3rem;
  height: 3rem;
  overflow: visible;
  fill: transparent;
  stroke: var(--stroke);
  stroke-linejoin: bevel;
  stroke-dasharray: 12;
  animation: idle 4s linear infinite;
  transition: stroke 0.2s, fill 0.5s;
}
@keyframes idle {
  from {
    stroke-dashoffset: 24;
  }
}
.rating label:hover svg {
  stroke: var(--fill);
}
.rating input:checked ~ label svg {
  transition: 0s;
  animation: idle 4s linear infinite, yippee 0.75s backwards;
  fill: var(--fill);
  stroke: var(--fill);
  stroke-opacity: 0;
  stroke-dasharray: 0;
  stroke-linejoin: miter;
  stroke-width: 8px;
}
@keyframes yippee {
  0% {
    transform: scale(1);
    fill: var(--fill);
    fill-opacity: 0;
    stroke-opacity: 1;
    stroke: var(--stroke);
    stroke-dasharray: 10;
    stroke-width: 1px;
    stroke-linejoin: bevel;
  }
  30% {
    transform: scale(0);
    fill: var(--fill);
    fill-opacity: 0;
    stroke-opacity: 1;
    stroke: var(--stroke);
    stroke-dasharray: 10;
    stroke-width: 1px;
    stroke-linejoin: bevel;
  }
  30.1% {
    stroke: var(--fill);
    stroke-dasharray: 0;
    stroke-linejoin: miter;
    stroke-width: 8px;
  }
  60% {
    transform: scale(1.2);
    fill: var(--fill);
  }
}

.rating-container {
  margin: 1rem auto;
  max-width: 300px;
}

.rating-hint {
  height: 24px;
  text-align: center;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ratingInputs = document.querySelectorAll('.rating input');
    const ratingText = document.getElementById('rating-text');
    
    const ratingMessages = {
        1: 'Poor - Very disappointed',
        2: 'Fair - Below expectations',
        3: 'Good - Acceptable',
        4: 'Very Good - Above expectations',
        5: 'Excellent - Highly satisfied'
    };
    
    ratingInputs.forEach(input => {
        input.addEventListener('change', function() {
            const rating = this.value;
            ratingText.textContent = ratingMessages[rating];
            ratingText.classList.remove('text-muted');
            
            switch(parseInt(rating)) {
                case 1:
                    ratingText.className = 'small text-danger fw-bold';
                    break;
                case 2:
                    ratingText.className = 'small text-warning fw-bold';
                    break;
                case 3:
                    ratingText.className = 'small text-info fw-bold';
                    break;
                case 4:
                    ratingText.className = 'small text-primary fw-bold';
                    break;
                case 5:
                    ratingText.className = 'small text-success fw-bold';
                    break;
            }
        });
        
        input.nextElementSibling.addEventListener('mouseenter', function() {
            const rating = input.value;
            ratingText.textContent = ratingMessages[rating];
        });
    });
    
    document.querySelector('.rating').addEventListener('mouseleave', function() {
        const checkedInput = document.querySelector('.rating input:checked');
        if (checkedInput) {
            ratingText.textContent = ratingMessages[checkedInput.value];
        } else {
            ratingText.textContent = 'Select a rating';
            ratingText.className = 'small text-muted';
        }
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>