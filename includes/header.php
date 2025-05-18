<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pageTitle)) {
    $pageTitle = "Eigenman Shop";
}

$basePath = '/e-commerce/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Eigenman Shop</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/custom.css">
    
    <?php if (basename($_SERVER['PHP_SELF']) === 'index.php' && dirname($_SERVER['PHP_SELF']) === '/e-commerce'): ?>

    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/splash.css">
    <script src="<?php echo $basePath; ?>assets/js/splash.js" defer></script>
    <?php endif; ?>
</head>
<body>
    <?php if (basename($_SERVER['PHP_SELF']) === 'index.php' && dirname($_SERVER['PHP_SELF']) === '/e-commerce'): ?>
    <div id="splash-screen">
        <img src="<?php echo $basePath; ?>assets/images/eigenman_logo.png" alt="Eigenman Logo" class="splash-logo">
    </div>
    <?php endif; ?>

    <?php 
    include_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/navbar.php';
    
    include_once $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/includes/messages.php';
    ?>