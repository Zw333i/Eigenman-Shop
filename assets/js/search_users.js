// User search functionality for chat and messaging
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearchInput');
    const searchResults = document.getElementById('searchResults');
    let searchTimer;
    
    if (searchInput) {
        // Add input event listener with debounce
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const query = this.value.trim();
            
            // Clear results if query is empty
            if (query.length === 0) {
                searchResults.innerHTML = '';
                searchResults.classList.add('d-none');
                return;
            }
            
            // Wait for user to stop typing before sending request
            searchTimer = setTimeout(() => {
                if (query.length >= 2) {
                    searchUsers(query);
                }
            }, 500); // 500ms debounce
        });
        
        // Hide search results when clicking outside
        document.addEventListener('click', function(event) {
            if (!searchInput.contains(event.target) && !searchResults.contains(event.target)) {
                searchResults.classList.add('d-none');
            }
        });
    }
    
    // Function to search users via API
    function searchUsers(query) {
        fetch(`/api/search_users.php?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.users) {
                    displaySearchResults(data.users);
                } else {
                    searchResults.innerHTML = '<div class="p-3 text-center">No users found</div>';
                    searchResults.classList.remove('d-none');
                }
            })
            .catch(error => {
                console.error('Error searching users:', error);
                searchResults.innerHTML = '<div class="p-3 text-center">Error searching users</div>';
                searchResults.classList.remove('d-none');
            });
    }
    
    // Function to display search results
    function displaySearchResults(users) {
        searchResults.innerHTML = '';
        
        if (users.length === 0) {
            searchResults.innerHTML = '<div class="p-3 text-center">No users found</div>';
        } else {
            users.forEach(user => {
                const userElement = document.createElement('div');
                userElement.className = 'search-result-item p-2 d-flex align-items-center cursor-pointer hover-bg-light';
                userElement.innerHTML = `
                    <img src="${user.image}" alt="${user.name}" class="rounded-circle me-2" width="40" height="40">
                    <div>
                        <div class="fw-bold">${user.name}</div>
                        <div class="text-muted small">@${user.username}</div>
                    </div>
                `;
                
                // Add click handler to start conversation
                userElement.addEventListener('click', function() {
                    startConversation(user.id);
                    searchResults.classList.add('d-none');
                    searchInput.value = '';
                });
                
                searchResults.appendChild(userElement);
            });
        }
        
        searchResults.classList.remove('d-none');
    }
    
    // Function to start or open a conversation
    function startConversation(userId) {
        // For direct messaging, either redirect to chat page or open chat window
        window.location.href = `/chats/index.php?user=${userId}`;
    }
});