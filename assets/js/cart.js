// assets/js/cart.js

document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to all add-to-cart buttons with the class 'ajax-add-to-cart'
    const addToCartButtons = document.querySelectorAll('.ajax-add-to-cart');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const itemId = this.getAttribute('data-item-id');
            const quantity = this.getAttribute('data-quantity') || 1;
            
            addToCart(itemId, quantity, this);
        });
    });
    
    // Function to add item to cart via AJAX
    function addToCart(itemId, quantity, buttonElement) {
        // Show loading state
        const originalButtonHtml = buttonElement.innerHTML;
        buttonElement.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding...';
        buttonElement.disabled = true;
        
        // Create form data
        const formData = new FormData();
        formData.append('itemId', itemId);
        formData.append('quantity', quantity);
        formData.append('ajax', true);
        
        // Send AJAX request
        fetch('../cart/ajax_add.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Reset button state
            buttonElement.innerHTML = originalButtonHtml;
            buttonElement.disabled = false;
            
            // Show notification
            showNotification(data.success ? 'success' : 'error', data.message);
            
            // Update cart count if successful
            if (data.success && data.cartCount) {
                updateCartCount(data.cartCount);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            buttonElement.innerHTML = originalButtonHtml;
            buttonElement.disabled = false;
            showNotification('error', 'An error occurred. Please try again.');
        });
    }
    
    // Function to show notification
    function showNotification(type, message) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} notification-toast`;
        notification.innerHTML = message;
        
        // Add to the DOM
        document.body.appendChild(notification);
        
        // Show with animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Auto hide after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 500);
        }, 3000);
    }
    
    // Function to update cart count in the navbar
    function updateCartCount(count) {
        const cartCountElements = document.querySelectorAll('.cart-count');
        
        cartCountElements.forEach(element => {
            if (count > 0) {
                element.textContent = count;
                element.classList.remove('d-none');
            } else {
                element.classList.add('d-none');
            }
        });
    }
});

// CSS to add to your stylesheet
/*
.notification-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1050;
    max-width: 350px;
    transform: translateX(400px);
    transition: transform 0.3s ease-in-out;
}

.notification-toast.show {
    transform: translateX(0);
}
*/