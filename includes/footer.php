<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/functions.php';
}
?>

<footer class="mt-5 py-5 bg-dark text-white">
    <div class="container">
        <div class="row">
            <div class="col-md-3 mb-4">
                <h5>Eigenman</h5>
                <p class="text-muted">Your Ultimate Online Store!<br><br> Powered By:<br>Jajyo, Fabby, Deimon, Aklas, and Zwei</p>
            </div>
            <div class="col-md-3 mb-4">
                <h5>Quick Links</h5>
                <ul class="list-unstyled">
                    <li><a href="/e-commerce/index.php" class="text-muted">Home</a></li>
                    <li><a href="/e-commerce/product/index.php" class="text-muted">Products</a></li>
                    <li><a href="/e-commerce/cart/view.php" class="text-muted">Cart</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="/e-commerce/auth/profile.php" class="text-muted">My Account</a></li>
                    <?php else: ?>
                        <li><a href="/e-commerce/auth/login.php" class="text-muted">Login</a></li>
                        <li><a href="/e-commerce/auth/register.php" class="text-muted">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h5>Customer Service</h5>
                <ul class="list-unstyled">
                    <li><a href="#" class="text-muted">Contact Us</a></li>
                    <li><a href="#" class="text-muted">FAQs</a></li>
                    <li><a href="#" class="text-muted">Shipping Policy</a></li>
                    <li><a href="#" class="text-muted">Return Policy</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h5>Connect With Us</h5>
                <div class="social-icons">
                    <a href="#" class="text-muted me-2"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-muted me-2"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-muted me-2"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-muted me-2"><i class="fab fa-pinterest"></i></a>
                </div>
                <p class="mt-3 text-muted">Subscribe to our newsletter</p>
                <form>
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Your email">
                        <button class="btn btn-primary" type="submit">Subscribe</button>
                    </div>
                </form>
            </div>
        </div>
        <hr class="my-4 bg-secondary">
        <div class="row">
            <div class="col-md-6">
                <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> Eigenman Shop. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="text-muted mb-0">
                    <a href="#" class="text-muted me-2">Privacy Policy</a>
                    <a href="#" class="text-muted me-2">Terms of Service</a>
                </p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="/e-commerce/assets/js/main.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

<script>
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: 5000
    };
    
    // Display PHP messages with toastr
    <?php
    if (isset($_SESSION['success_message'])) {
        echo "toastr.success('" . $_SESSION['success_message'] . "');";
        unset($_SESSION['success_message']);
    }
    
    if (isset($_SESSION['error_message'])) {
        echo "toastr.error('" . $_SESSION['error_message'] . "');";
        unset($_SESSION['error_message']);
    }
    
    if (isset($_SESSION['info_message'])) {
        echo "toastr.info('" . $_SESSION['info_message'] . "');";
        unset($_SESSION['info_message']);
    }
    
    if (isset($_SESSION['warning_message'])) {
        echo "toastr.warning('" . $_SESSION['warning_message'] . "');";
        unset($_SESSION['warning_message']);
    }
    ?>
</script>
</body>
</html>