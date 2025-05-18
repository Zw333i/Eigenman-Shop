// Global variables
let currentChatUserId = null;
let lastMessageId = 0;
let isLoadingMessages = false;
let hasMoreMessages = true;

// Initialize chat when document is ready
$(document).ready(function() {
    // Load initial conversations list
    loadConversations();
    
    // Set up the message form submit handler
    $('#messageForm').submit(function(e) {
        e.preventDefault();
        sendMessage();
    });
    
    // Load more messages when scrolling up
    $('.chat-messages').scroll(function() {
        if ($(this).scrollTop() === 0 && hasMoreMessages && !isLoadingMessages && currentChatUserId) {
            loadMoreMessages();
        }
    });
    
    // Search users
    $('#userSearch').keyup(function() {
        const query = $(this).val().trim();
        if (query.length >= 2) {
            searchUsers(query);
        } else {
            loadConversations();
        }
    });
});

// Load user conversations
function loadConversations() {
    $.ajax({
        url: '/e-commerce/api/get_conversations.php',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                displayConversations(data.conversations);
            } else {
                console.error('Error loading conversations:', data.error);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', error);
        }
    });
}

// Display conversations list
function displayConversations(conversations) {
    const $conversationsList = $('.conversations-list');
    $conversationsList.empty();
    
    if (conversations.length === 0) {
        $conversationsList.append('<div class="no-conversations">No conversations yet</div>');
        return;
    }
    
    conversations.forEach(function(conversation) {
        const unreadBadge = conversation.unread_count > 0 ? 
            `<span class="badge badge-danger">${conversation.unread_count}</span>` : '';
        
        const lastMessageTime = conversation.last_message_time ? 
            `<span class="text-muted small">${formatTimestamp(conversation.last_message_time)}</span>` : '';
        
        const profilePic = conversation.profilePicture ? 
            `/e-commerce/${conversation.profilePicture}` : 
            '/e-commerce/assets/images/profile-placeholder.jpg';
        
        const conversationHtml = `
            <div class="conversation-item" data-user-id="${conversation.userId}">
                <div class="conversation-avatar">
                    <img src="${profilePic}" alt="${conversation.username}" class="avatar-img">
                </div>
                <div class="conversation-info">
                    <div class="conversation-name">
                        ${conversation.firstname} ${conversation.lastname}
                        ${unreadBadge}
                    </div>
                    <div class="conversation-preview">
                        ${lastMessageTime}
                    </div>
                </div>
            </div>
        `;
        
        $conversationsList.append(conversationHtml);
    });
    
    // Add click event to conversation items
    $('.conversation-item').click(function() {
        const userId = $(this).data('user-id');
        openChat(userId);
        
        // Add active class to this conversation
        $('.conversation-item').removeClass('active');
        $(this).addClass('active');
        $(this).find('.badge').remove(); // Remove unread badge
    });
    
    // If we were viewing a chat, keep it open
    if (currentChatUserId) {
        $(`.conversation-item[data-user-id="${currentChatUserId}"]`).addClass('active');
    }
}

// Open chat with specific user
function openChat(userId) {
    currentChatUserId = userId;
    lastMessageId = 0;
    hasMoreMessages = true;
    
    // Clear the chat window
    $('.chat-messages').empty();
    
    // Show the chat area
    $('.chat-container').removeClass('d-none');
    
    // On mobile, hide the conversations list
    if (window.innerWidth < 768) {
        $('.conversations-section').addClass('d-none');
        $('.chat-section').removeClass('d-none');
    }
    
    // Show loading indicator
    $('.chat-messages').append('<div class="text-center p-3"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>');
    
    // Load messages
    $.ajax({
        url: '/e-commerce/api/get_messages.php',
        type: 'GET',
        data: {
            userId: userId
        },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                // Update the chat header
                updateChatHeader(data.user);
                
                // Store has more messages flag
                hasMoreMessages = data.hasMore;
                
                // Display messages
                $('.chat-messages').empty();
                if (data.messages.length === 0) {
                    $('.chat-messages').append('<div class="no-messages text-center p-3">No messages yet. Start a conversation!</div>');
                } else {
                    displayMessages(data.messages);
                    
                    // Scroll to bottom of chat
                    const chatMessages = document.querySelector('.chat-messages');
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                    
                    // Store the ID of the oldest message for loading more
                    if (data.messages.length > 0) {
                        lastMessageId = data.messages[0].chatId;
                    }
                }
            } else {
                console.error('Error loading messages:', data.error);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', error);
            $('.chat-messages').empty().append('<div class="text-center text-danger p-3">Error loading messages. Please try again.</div>');
        }
    });
    
    // Focus on message input
    $('#messageInput').focus();
}

// Update the chat header with user info
function updateChatHeader(user) {
    const profilePic = user.profilePicture ? 
        `/e-commerce/${user.profilePicture}` : 
        '/e-commerce/assets/images/profile-placeholder.jpg';
    
    $('.chat-header-avatar').html(`<img src="${profilePic}" alt="${user.username}" class="avatar-img">`);
    $('.chat-header-title').text(`${user.firstname} ${user.lastname}`);
    
    // Show back button on mobile
    $('.back-to-conversations').removeClass('d-none d-md-none');
    $('.back-to-conversations').click(function() {
        $('.chat-section').addClass('d-none');
        $('.conversations-section').removeClass('d-none');
    });
}

// Display messages in the chat window
function displayMessages(messages) {
    const $chatMessages = $('.chat-messages');
    
    messages.forEach(function(message) {
        const isCurrentUser = message.is_sender === 1;
        const messageClass = isCurrentUser ? 'message-outgoing' : 'message-incoming';
        const alignClass = isCurrentUser ? 'align-self-end' : 'align-self-start';
        const bubbleClass = isCurrentUser ? 'bg-primary text-white' : 'bg-light';
        
        const profilePic = message.sender_picture ? 
            `/e-commerce/${message.sender_picture}` : 
            '/e-commerce/assets/images/profile-placeholder.jpg';
        
        // Only show avatar for incoming messages
        const avatarHtml = !isCurrentUser ? 
            `<div class="message-avatar">
                <img src="${profilePic}" alt="${message.sender_username}" class="avatar-img-sm">
            </div>` : '';
        
        const messageHtml = `
            <div class="message ${messageClass} ${alignClass}">
                ${avatarHtml}
                <div class="message-content">
                    <div class="message-bubble ${bubbleClass}">
                        ${message.message}
                    </div>
                    <div class="message-time small text-muted">
                        ${message.formatted_time}
                    </div>
                </div>
            </div>
        `;
        
        $chatMessages.append(messageHtml);
    });
}

// Load more messages when scrolling up
function loadMoreMessages() {
    if (!currentChatUserId || !hasMoreMessages || isLoadingMessages) return;
    
    isLoadingMessages = true;
    
    // Add loading indicator at the top
    $('.chat-messages').prepend('<div class="loading-more text-center p-2"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="sr-only">Loading...</span></div></div>');
    
    // Get scroll position before loading more messages
    const chatMessages = document.querySelector('.chat-messages');
    const oldScrollHeight = chatMessages.scrollHeight;
    
    $.ajax({
        url: '/e-commerce/api/get_messages.php',
        type: 'GET',
        data: {
            userId: currentChatUserId,
            lastId: lastMessageId
        },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                // Remove loading indicator
                $('.loading-more').remove();
                
                // Store has more messages flag
                hasMoreMessages = data.hasMore;
                
                if (data.messages.length > 0) {
                    // Prepend messages
                    const $chatMessages = $('.chat-messages');
                    const scrollPos = chatMessages.scrollTop;
                    
                    data.messages.forEach(function(message) {
                        const isCurrentUser = message.is_sender === 1;
                        const messageClass = isCurrentUser ? 'message-outgoing' : 'message-incoming';
                        const alignClass = isCurrentUser ? 'align-self-end' : 'align-self-start';
                        const bubbleClass = isCurrentUser ? 'bg-primary text-white' : 'bg-light';
                        
                        const profilePic = message.sender_picture ? 
                            `/e-commerce/${message.sender_picture}` : 
                            '/e-commerce/assets/images/profile-placeholder.jpg';
                        
                        // Only show avatar for incoming messages
                        const avatarHtml = !isCurrentUser ? 
                            `<div class="message-avatar">
                                <img src="${profilePic}" alt="${message.sender_username}" class="avatar-img-sm">
                            </div>` : '';
                        
                        const messageHtml = `
                            <div class="message ${messageClass} ${alignClass}">
                                ${avatarHtml}
                                <div class="message-content">
                                    <div class="message-bubble ${bubbleClass}">
                                        ${message.message}
                                    </div>
                                    <div class="message-time small text-muted">
                                        ${message.formatted_time}
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        $chatMessages.prepend(messageHtml);
                    });
                    
                    // Store the ID of the oldest message
                    lastMessageId = data.messages[0].chatId;
                    
                    // Maintain scroll position
                    chatMessages.scrollTop = chatMessages.scrollHeight - oldScrollHeight + scrollPos;
                }
            } else {
                console.error('Error loading more messages:', data.error);
                $('.loading-more').remove();
            }
            
            isLoadingMessages = false;
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', error);
            $('.loading-more').remove();
            isLoadingMessages = false;
        }
    });
}

// Send a message
function sendMessage() {
    const message = $('#messageInput').val().trim();
    
    if (!message || !currentChatUserId) return;
    
    // Clear input
    $('#messageInput').val('');
    
    // Temporarily show the message in the chat
    const tempId = 'temp_' + Date.now();
    const tempMessageHtml = `
        <div id="${tempId}" class="message message-outgoing align-self-end">
            <div class="message-content">
                <div class="message-bubble bg-primary text-white">
                    ${message}
                </div>
                <div class="message-time small text-muted">
                    <span class="sending">Sending...</span>
                </div>
            </div>
        </div>
    `;
    
    $('.chat-messages').append(tempMessageHtml);
    
    // Scroll to bottom
    const chatMessages = document.querySelector('.chat-messages');
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    // Send message to server
    $.ajax({
        url: '/e-commerce/api/send_message.php',
        type: 'POST',
        data: {
            receiverId: currentChatUserId,
            message: message
        },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                // Update the temporary message
                $(`#${tempId} .sending`).text(data.time);
                $(`#${tempId}`).attr('id', ''); // Remove temp ID
                
                // If this was a new conversation, refresh conversations list
                loadConversations();
            } else {
                // Show error
                $(`#${tempId} .sending`).html('<span class="text-danger">Failed to send</span>');
                console.error('Error sending message:', data.error);
            }
        },
        error: function(xhr, status, error) {
            // Show error
            $(`#${tempId} .sending`).html('<span class="text-danger">Failed to send</span>');
            console.error('AJAX error:', error);
        }
    });
}

// Search for users
function searchUsers(query) {
    $.ajax({
        url: '/e-commerce/api/search_users.php',
        type: 'GET',
        data: {
            query: query
        },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                displaySearchResults(data.users);
            } else {
                console.error('Error searching users:', data.error);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', error);
        }
    });
}

// Display search results
function displaySearchResults(users) {
    const $conversationsList = $('.conversations-list');
    $conversationsList.empty();
    
    if (users.length === 0) {
        $conversationsList.append('<div class="no-conversations p-3">No users found</div>');
        return;
    }
    
    users.forEach(function(user) {
        const profilePic = user.profilePicture ? 
            `/e-commerce/${user.profilePicture}` : 
            '/e-commerce/assets/images/profile-placeholder.jpg';
        
        const userHtml = `
            <div class="conversation-item" data-user-id="${user.userId}">
                <div class="conversation-avatar">
                    <img src="${profilePic}" alt="${user.username}" class="avatar-img">
                </div>
                <div class="conversation-info">
                    <div class="conversation-name">
                        ${user.firstname} ${user.lastname}
                    </div>
                    <div class="conversation-preview">
                        <small class="text-muted">@${user.username}</small>
                    </div>
                </div>
            </div>
        `;
        
        $conversationsList.append(userHtml);
    });
    
    // Add click event to search results
    $('.conversation-item').click(function() {
        const userId = $(this).data('user-id');
        openChat(userId);
        
        // Add active class to this result
        $('.conversation-item').removeClass('active');
        $(this).addClass('active');
    });
}

// Helper function to format timestamps
function formatTimestamp(timestamp) {
    const now = new Date();
    const messageDate = new Date(timestamp);
    const diffMs = now - messageDate;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) {
        return 'Just now';
    } else if (diffMins < 60) {
        return `${diffMins}m ago`;
    } else if (diffHours < 24) {
        return `${diffHours}h ago`;
    } else if (diffDays === 1) {
        return 'Yesterday';
    } else if (diffDays < 7) {
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        return days[messageDate.getDay()];
    } else {
        const month = messageDate.toLocaleString('default', { month: 'short' });
        return `${month} ${messageDate.getDate()}`;
    }
}

// Check for new messages periodically
setInterval(function() {
    // Refresh conversations list
    loadConversations();
    
    // If a chat is open, refresh messages
    if (currentChatUserId) {
        $.ajax({
            url: '/e-commerce/api/get_new_messages.php',
            type: 'GET',
            data: {
                userId: currentChatUserId,
                lastId: $('.message:last-child').data('message-id') || 0
            },
            dataType: 'json',
            success: function(data) {
                if (data.success && data.messages.length > 0) {
                    // Add new messages
                    displayMessages(data.messages);
                    
                    // Scroll to bottom if already at bottom
                    const chatMessages = document.querySelector('.chat-messages');
                    const isAtBottom = chatMessages.scrollHeight - chatMessages.clientHeight <= chatMessages.scrollTop + 50;
                    
                    if (isAtBottom) {
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    } else {
                        // Show new message indicator
                        if (!$('.new-messages-indicator').length) {
                            $('.chat-messages-container').append('<div class="new-messages-indicator">New messages ↓</div>');
                            
                            // Scroll to bottom when clicking indicator
                            $('.new-messages-indicator').click(function() {
                                chatMessages.scrollTop = chatMessages.scrollHeight;
                                $(this).remove();
                            });
                        }
                    }
                }
            }
        });
    }
}, 10000); // Check every 10 seconds