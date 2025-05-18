<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eigenman - Online Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/splash.css">

    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/splash.js" defer></script>
</head>
<body>
    <div id="splash-screen">
        <img src="assets/images/eigenman_logo.png" alt="Eigenman Logo" class="splash-logo">
    </div>

    <?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    require_once 'config/database.php';
    
    include_once 'includes/navbar.php';
    
    $featuredProducts = [];
    $featuredQuery = "SELECT i.*, m.storeName FROM Item i 
                     JOIN Merchant m ON i.merchantId = m.merchantId 
                     ORDER BY i.itemId DESC LIMIT 4";
    $featuredResult = $conn->query($featuredQuery);
    
    while ($row = $featuredResult->fetch_assoc()) {
        $featuredProducts[] = $row;
    }
    ?>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6" data-aos="fade-right" data-aos-duration="1000">
                <h1 class="display-4 fw-bold text-light mb-4">
                    <span class="typing">Welcome to Eigenman Shop</span>
                </h1>
                    <p class="lead mb-4 text-light mb-4">Your one-stop destination for all your shopping needs</p>
                    <form action="product/index.php" method="GET" class="mb-4">
                        <div class="input-group">
                            <input type="text" class="form-control form-control-lg" name="search" placeholder="What are you looking for?" required>
                            <input type="hidden" name="brand" value="">
                            <input type="hidden" name="sort" value="">
                            <button class="btn btn-primary btn-lg" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="product/index.php" class="btn btn-outline-primary">Browse Products</a>
                        <?php if (!isset($_SESSION['userId'])) : ?>
                            <a href="auth/register.php" class="btn btn-primary">Join Now</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 text-center" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                    <img src="assets/images/banner.png" class="img-fluid rounded hero-img" alt="Shopping Banner">
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Categories -->
    <div class="container py-5">
        <div class="row">
            <div class="col-12">
                <h2 class="section-title" data-aos="fade-up">Popular Categories</h2>
                <div class="row">
                    <div class="col-md-3 col-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <a href="product/index.php?search=&brand=Anker&sort=" class="text-decoration-none">
                            <div class="card category-card text-center py-4">
                                <div class="card-body">
                                    <i class="fas fa-laptop fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Electronics</h5>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <a href="product/index.php?search=&brand=Bench&sort=" class="text-decoration-none">
                            <div class="card category-card text-center py-4">
                                <div class="card-body">
                                    <i class="fas fa-tshirt fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Fashion</h5>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                        <a href="product/index.php?search=&brand=Mandaue+Foam&sort=" class="text-decoration-none">
                            <div class="card category-card text-center py-4">
                                <div class="card-body">
                                    <i class="fas fa-home fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Home & Living</h5>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                        <a href="product/index.php?search=&brand=Maybelline&sort=" class="text-decoration-none">
                            <div class="card category-card text-center py-4">
                                <div class="card-body">
                                    <i class="fas fa-spa fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Beauty</h5>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Products -->
    <div class="container py-5 bg-light rounded-3">
        <div class="row">
            <div class="col-12">
                <h2 class="section-title" data-aos="fade-up">Featured Products</h2>
                <div class="row" id="featured-products">
                    <?php if (empty($featuredProducts)): ?>
                        <div class="col-12 text-center">
                            <p>No featured products available at the moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($featuredProducts as $index => $product): ?>
                            <div class="col-md-4 col-lg-3 mb-4" data-aos="fade-up" data-aos-delay="<?php echo ($index + 1) * 100; ?>">
                                <div class="card h-100 product-card">
                                    <?php if (!empty($product['picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['picture']); ?>" class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['itemName']); ?>">
                                    <?php else: ?>
                                        <img src="assets/images/product-placeholder.jpg" class="card-img-top product-img" alt="Product">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title text-truncate"><?php echo htmlspecialchars($product['itemName']); ?></h5>
                                        <p class="card-text">
                                            <span class="text-muted"><?php echo htmlspecialchars($product['brand']); ?></span><br>
                                            <span class="fw-bold text-primary">₱<?php echo number_format($product['itemPrice'], 2); ?></span>
                                        </p>
                                    </div>
                                    <div class="card-footer bg-white border-top-0">
                                        <div class="d-flex justify-content-between">
                                            <a href="product/details.php?id=<?php echo $product['itemId']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                            <?php if ($product['quantity'] > 0): ?>
                                                <a href="cart/add.php?id=<?php echo $product['itemId']; ?>&qty=1" class="btn btn-sm btn-success">
                                                    <i class="fas fa-cart-plus"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="text-center mt-4" data-aos="fade-up">
                    <a href="product/index.php" class="btn btn-outline-primary">View All Products</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Special Offers Section -->
    <div class="container py-5">
        <div class="row align-items-center">
            <div class="col-md-6" data-aos="fade-right" data-aos-duration="1000">
                <h2 class="section-title">Special Offers</h2>
                <div class="card special-offer-card mb-4">
                    <div class="card-body p-4">
                        <h3 class="text-primary">New User Discount</h3>
                        <p>Sign up today and get 10% off on your first purchase!</p>
                        <a href="/e-commerce/auth/register.php" class="btn btn-primary">Register Now</a>
                    </div>
                </div>
                <div class="card special-offer-card">
                    <div class="card-body p-4">
                        <h3 class="text-primary">Free Shipping</h3>
                        <p>On all orders above ₱1,000 nationwide!</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 text-center" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                <img src="assets/images/special-offer.png" class="img-fluid rounded" alt="Special Offers">
            </div>
        </div>
    </div>

    <!-- Why Choose Us -->
    <div class="container py-5 bg-light rounded-3">
        <div class="row">
            <div class="col-12 text-center mb-4">
                <h2 class="section-title mx-auto" data-aos="fade-up" style="width: fit-content;">Why Choose Eigenman?</h2>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card h-100 feature-card text-center py-4">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-3x text-primary mb-3"></i>
                        <h5>Verified Merchants</h5>
                        <p class="card-text">All our merchants are verified to ensure safe shopping.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="card h-100 feature-card text-center py-4">
                    <div class="card-body">
                        <i class="fas fa-shipping-fast fa-3x text-primary mb-3"></i>
                        <h5>Fast Delivery</h5>
                        <p class="card-text">Quick and reliable shipping to your doorstep.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4" data-aos="fade-up" data-aos-delay="300">
                <div class="card h-100 feature-card text-center py-4">
                    <div class="card-body">
                        <i class="fas fa-undo fa-3x text-primary mb-3"></i>
                        <h5>Easy Returns</h5>
                        <p class="card-text">Hassle-free return policy for your peace of mind.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4" data-aos="fade-up" data-aos-delay="400">
                <div class="card h-100 feature-card text-center py-4">
                    <div class="card-body">
                        <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                        <h5>24/7 Support</h5>
                        <p class="card-text">Our customer support team is always available to help.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subscribe Newsletter -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center" data-aos="fade-up">
                <h2 class="section-title mx-auto" style="width: fit-content;">Stay Updated</h2>
                <p class="mb-4">Subscribe to our newsletter to get updates on our latest offers!</p>
                <form class="row g-3 justify-content-center">
                    <div class="col-md-8">
                        <input type="email" class="form-control form-control-lg" placeholder="Your email address">
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary btn-lg w-100">Subscribe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include_once 'includes/footer.php'; ?>

    <div id="back-to-top">
        <i class="fas fa-arrow-up"></i>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>

    <script>
        AOS.init({
            once: true,
            duration: 800
        });

        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const splashScreen = document.getElementById('splash-screen');
                splashScreen.style.opacity = '0';
                
                setTimeout(function() {
                    splashScreen.style.display = 'none';
                }, 500);
            }, 2000);
        });

        window.addEventListener('scroll', function() {
            const backToTopButton = document.getElementById('back-to-top');
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('show');
            } else {
                backToTopButton.classList.remove('show');
            }
        });

        document.getElementById('back-to-top').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>
</body>
</html>