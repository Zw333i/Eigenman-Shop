/**
 * ShopHub - E-commerce Website JavaScript
 */

// Document Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Add to cart functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const itemId = this.getAttribute('data-item-id');
            const quantityInput = document.querySelector(`#quantity-${itemId}`);
            const quantity = quantityInput ? parseInt(quantityInput.value) : 1;
            
            // Check if user is logged in
            if (!isUserLoggedIn()) {
                window.location.href = '/e-commerce/auth/login.php';
                return;
            }
            
            // AJAX call to add item to cart
            fetch('/e-commerce/cart/add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `item_id=${itemId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success(data.message);
                    updateCartCount(data.cart_count);
                } else {
                    toastr.error(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toastr.error('An error occurred while adding the item to cart.');
            });
        });
    });
    
    // Update cart quantity
    const cartQuantityInputs = document.querySelectorAll('.cart-quantity');
    
    cartQuantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            const itemId = this.getAttribute('data-item-id');
            const quantity = parseInt(this.value);
            
            updateCartItem(itemId, quantity);
        });
    });
    
    // Remove from cart
    const removeButtons = document.querySelectorAll('.remove-from-cart');
    
    removeButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const itemId = this.getAttribute('data-item-id');
            
            // AJAX call to remove item from cart
            fetch('/e-commerce/cart/update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `item_id=${itemId}&quantity=0`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success(data.message);
                    // Remove the cart item from DOM
                    document.querySelector(`#cart-item-${itemId}`).remove();
                    updateCartTotal(data.cart_total);
                    updateCartCount(data.cart_count);
                    
                    // If cart is empty, show empty cart message
                    if (data.cart_count === 0) {
                        const cartItems = document.querySelector('.cart-items');
                        const emptyCartMessage = document.createElement('div');
                        emptyCartMessage.classList.add('alert', 'alert-info');
                        emptyCartMessage.textContent = 'Your cart is empty.';
                        cartItems.appendChild(emptyCartMessage);
                    }
                } else {
                    toastr.error(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toastr.error('An error occurred while removing the item from cart.');
            });
        });
    });
    
    // Chat message form
    const chatForm = document.querySelector('#chat-form');
    
    if (chatForm) {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const messageInput = document.querySelector('#message');
            const message = messageInput.value.trim();
            const receiverId = this.getAttribute('data-receiver-id');
            
            if (message === '') return;
            
            // AJAX call to send message
            fetch('/e-commerce/chats/send.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `receiver_id=${receiverId}&message=${encodeURIComponent(message)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add message to chat
                    addMessageToChat('sent', message, data.timestamp);
                    
                    // Clear input
                    messageInput.value = '';
                } else {
                    toastr.error(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                toastr.error('An error occurred while sending the message.');
            });
        });
    }
    
    // Search form
    const searchForm = document.querySelector('#search-form');
    
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const searchInput = document.querySelector('#search-input');
            const keyword = searchInput.value.trim();
            
            if (keyword === '') {
                e.preventDefault();
                toastr.warning('Please enter a search term.');
            }
        });
    }
});

/**
 * Check if user is logged in
 * @returns {boolean}
 */
function isUserLoggedIn() {
    // This is a simple check that will be overridden by server-side check
    return document.body.classList.contains('logged-in');
}

/**
 * Update cart item quantity
 * @param {number} itemId - Item ID
 * @param {number} quantity - New quantity
 */
function updateCartItem(itemId, quantity) {
    // AJAX call to update cart item
    fetch('/e-commerce/cart/update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `item_id=${itemId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            toastr.success(data.message);
            
            // Update item total price
            document.querySelector(`#item-total-${itemId}`).textContent = data.item_total;
            
            // Update cart total
            updateCartTotal(data.cart_total);
            
            // Update cart count
            updateCartCount(data.cart_count);
        } else {
            toastr.error(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toastr.error('An error occurred while updating the cart.');
    });
}

/**
 * Update cart total
 * @param {string} total - New cart total
 */
function updateCartTotal(total) {
    const cartTotalElement = document.querySelector('#cart-total');
    
    if (cartTotalElement) {
        cartTotalElement.textContent = total;
    }
}

/**
 * Update cart count in navbar
 * @param {number} count - New cart count
 */
function updateCartCount(count) {
    const cartBadge = document.querySelector('#cart-badge');
    
    if (cartBadge) {
        if (count > 0) {
            cartBadge.textContent = count;
            cartBadge.style.display = 'inline-block';
        } else {
            cartBadge.style.display = 'none';
        }
    }
}

/**
 * Add message to chat
 * @param {string} type - Message type (sent/received)
 * @param {string} message - Message content
 * @param {string} timestamp - Message timestamp
 */
function addMessageToChat(type, message, timestamp) {
    const chatMessages = document.querySelector('.chat-messages');
    
    if (!chatMessages) return;
    
    const messageDiv = document.createElement('div');
    messageDiv.classList.add('message', `message-${type}`);
    
    const messageContent = document.createElement('div');
    messageContent.classList.add('message-content');
    messageContent.textContent = message;
    
    const messageTime = document.createElement('div');
    messageTime.classList.add('message-time', 'text-muted', 'small');
    messageTime.textContent = timestamp;
    
    messageDiv.appendChild(messageContent);
    messageDiv.appendChild(messageTime);
    
    chatMessages.appendChild(messageDiv);
    
    // Scroll to bottom
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

/**
 * Form validation for various forms
 */
document.addEventListener("DOMContentLoaded", function() {
    // Registration form validation
    const registerForm = document.getElementById('register-form');
    
    if (registerForm) {
        registerForm.addEventListener('submit', function(event) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                event.preventDefault();
                toastr.error('Passwords do not match');
            }
        });
    }
    
    // Profile update form validation
    const profileForm = document.getElementById('profile-form');
    
    if (profileForm) {
        profileForm.addEventListener('submit', function(event) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== '' && newPassword !== confirmPassword) {
                event.preventDefault();
                toastr.error('New passwords do not match');
            }
        });
    }
});