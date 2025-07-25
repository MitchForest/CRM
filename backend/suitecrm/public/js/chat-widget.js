/**
 * SuiteCRM AI Chat Widget
 * Embeddable chat widget for customer support
 * 
 * Usage:
 * <script>
 *   window.SUITECRM_CHAT = {
 *     api_url: 'https://your-crm.com',
 *     position: 'bottom-right', // or 'bottom-left'
 *     theme: 'light', // or 'dark'
 *     welcome_message: 'Hi! How can I help you today?'
 *   };
 * </script>
 * <script src="https://your-crm.com/public/js/chat-widget.js" async></script>
 */

(function() {
    'use strict';
    
    // Configuration
    const config = window.SUITECRM_CHAT || {};
    const API_BASE = config.api_url || 'http://localhost:8080';
    const API_ENDPOINT = '/api/v8/ai/chat';
    
    // Chat state
    let chatWidget = null;
    let chatContainer = null;
    let messageContainer = null;
    let isOpen = false;
    let conversationId = null;
    let messages = [];
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        createChatWidget();
        loadChatHistory();
        injectStyles();
    }
    
    function createChatWidget() {
        // Create main container
        chatWidget = document.createElement('div');
        chatWidget.id = 'suitecrm-chat-widget';
        chatWidget.className = `chat-widget ${config.position || 'bottom-right'} ${config.theme || 'light'}`;
        
        // Create chat button
        const chatButton = document.createElement('button');
        chatButton.className = 'chat-button';
        chatButton.innerHTML = `
            <svg class="chat-icon" viewBox="0 0 24 24" width="24" height="24">
                <path fill="currentColor" d="M12,3C17.5,3 22,6.58 22,11C22,15.42 17.5,19 12,19C10.76,19 9.57,18.82 8.47,18.5C5.55,21 2,21 2,21C4.33,18.67 4.7,17.1 4.75,16.5C3.05,15.07 2,13.13 2,11C2,6.58 6.5,3 12,3Z"/>
            </svg>
            <span class="chat-button-text">Chat</span>
            <span class="chat-notification" style="display: none;">1</span>
        `;
        chatButton.addEventListener('click', toggleChat);
        
        // Create chat container
        chatContainer = document.createElement('div');
        chatContainer.className = 'chat-container';
        chatContainer.style.display = 'none';
        
        // Create chat header
        const chatHeader = document.createElement('div');
        chatHeader.className = 'chat-header';
        chatHeader.innerHTML = `
            <div class="chat-header-content">
                <h3>Support Chat</h3>
                <p>We typically reply in minutes</p>
            </div>
            <button class="chat-close" aria-label="Close chat">
                <svg viewBox="0 0 24 24" width="20" height="20">
                    <path fill="currentColor" d="M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z"/>
                </svg>
            </button>
        `;
        chatHeader.querySelector('.chat-close').addEventListener('click', toggleChat);
        
        // Create message container
        messageContainer = document.createElement('div');
        messageContainer.className = 'chat-messages';
        
        // Add welcome message
        if (config.welcome_message) {
            addMessage('bot', config.welcome_message);
        }
        
        // Create input area
        const inputArea = document.createElement('div');
        inputArea.className = 'chat-input-area';
        inputArea.innerHTML = `
            <form class="chat-form">
                <input type="text" class="chat-input" placeholder="Type your message..." />
                <button type="submit" class="chat-send" aria-label="Send message">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M2,21L23,12L2,3V10L17,12L2,14V21Z"/>
                    </svg>
                </button>
            </form>
        `;
        
        const form = inputArea.querySelector('.chat-form');
        form.addEventListener('submit', handleSubmit);
        
        // Assemble chat container
        chatContainer.appendChild(chatHeader);
        chatContainer.appendChild(messageContainer);
        chatContainer.appendChild(inputArea);
        
        // Assemble widget
        chatWidget.appendChild(chatButton);
        chatWidget.appendChild(chatContainer);
        
        // Add to page
        document.body.appendChild(chatWidget);
    }
    
    function toggleChat() {
        isOpen = !isOpen;
        
        if (isOpen) {
            chatContainer.style.display = 'flex';
            chatWidget.classList.add('open');
            
            // Focus input
            setTimeout(() => {
                const input = chatContainer.querySelector('.chat-input');
                input.focus();
            }, 100);
            
            // Clear notification
            const notification = chatWidget.querySelector('.chat-notification');
            notification.style.display = 'none';
            notification.textContent = '0';
        } else {
            chatContainer.style.display = 'none';
            chatWidget.classList.remove('open');
        }
    }
    
    async function handleSubmit(e) {
        e.preventDefault();
        
        const input = e.target.querySelector('.chat-input');
        const message = input.value.trim();
        
        if (!message) return;
        
        // Add user message
        addMessage('user', message);
        
        // Clear input
        input.value = '';
        
        // Show typing indicator
        showTypingIndicator();
        
        try {
            // Send to API
            const response = await fetch(`${API_BASE}${API_ENDPOINT}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    messages: messages.map(m => ({
                        role: m.type === 'user' ? 'user' : 'assistant',
                        content: m.content
                    })),
                    conversation_id: conversationId,
                    context: {
                        page_url: window.location.href,
                        visitor_id: getVisitorId()
                    }
                })
            });
            
            if (!response.ok) {
                throw new Error('Chat request failed');
            }
            
            const data = await response.json();
            
            // Remove typing indicator
            hideTypingIndicator();
            
            // Add bot response
            addMessage('bot', data.response);
            
            // Update conversation ID
            if (data.conversation_id) {
                conversationId = data.conversation_id;
                saveChatHistory();
            }
            
            // Handle special actions
            if (data.handoff_required) {
                addMessage('system', 'Connecting you with a human agent...');
            }
            
        } catch (error) {
            console.error('Chat error:', error);
            hideTypingIndicator();
            addMessage('bot', 'I apologize, but I\'m having trouble connecting. Please try again in a moment.');
        }
    }
    
    function addMessage(type, content) {
        const message = {
            type: type,
            content: content,
            timestamp: new Date()
        };
        
        messages.push(message);
        
        // Create message element
        const messageEl = document.createElement('div');
        messageEl.className = `chat-message ${type}`;
        
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.textContent = content;
        
        const time = document.createElement('div');
        time.className = 'message-time';
        time.textContent = formatTime(message.timestamp);
        
        messageEl.appendChild(bubble);
        messageEl.appendChild(time);
        
        messageContainer.appendChild(messageEl);
        
        // Scroll to bottom
        messageContainer.scrollTop = messageContainer.scrollHeight;
        
        // Show notification if chat is closed
        if (!isOpen && type === 'bot') {
            showNotification();
        }
        
        // Save to local storage
        saveChatHistory();
    }
    
    function showTypingIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'chat-message bot typing-indicator';
        indicator.innerHTML = `
            <div class="message-bubble">
                <span></span>
                <span></span>
                <span></span>
            </div>
        `;
        messageContainer.appendChild(indicator);
        messageContainer.scrollTop = messageContainer.scrollHeight;
    }
    
    function hideTypingIndicator() {
        const indicator = messageContainer.querySelector('.typing-indicator');
        if (indicator) {
            indicator.remove();
        }
    }
    
    function showNotification() {
        const notification = chatWidget.querySelector('.chat-notification');
        const count = parseInt(notification.textContent) || 0;
        notification.textContent = count + 1;
        notification.style.display = 'flex';
    }
    
    function formatTime(date) {
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${hours}:${minutes}`;
    }
    
    function getVisitorId() {
        let visitorId = localStorage.getItem('suitecrm_visitor_id');
        if (!visitorId) {
            visitorId = generateUUID();
            localStorage.setItem('suitecrm_visitor_id', visitorId);
        }
        return visitorId;
    }
    
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    
    function saveChatHistory() {
        const chatData = {
            conversationId: conversationId,
            messages: messages.slice(-20) // Keep last 20 messages
        };
        localStorage.setItem('suitecrm_chat', JSON.stringify(chatData));
    }
    
    function loadChatHistory() {
        const saved = localStorage.getItem('suitecrm_chat');
        if (saved) {
            try {
                const data = JSON.parse(saved);
                conversationId = data.conversationId;
                
                // Only load messages from the last 24 hours
                const oneDayAgo = new Date(Date.now() - 24 * 60 * 60 * 1000);
                data.messages.forEach(msg => {
                    if (new Date(msg.timestamp) > oneDayAgo) {
                        messages.push(msg);
                        // Don't show old messages in UI, just keep in memory
                    }
                });
            } catch (e) {
                console.error('Failed to load chat history:', e);
            }
        }
    }
    
    function injectStyles() {
        if (document.getElementById('suitecrm-chat-styles')) {
            return;
        }
        
        const style = document.createElement('style');
        style.id = 'suitecrm-chat-styles';
        style.textContent = `
            #suitecrm-chat-widget {
                position: fixed;
                z-index: 9999;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            
            #suitecrm-chat-widget.bottom-right {
                bottom: 20px;
                right: 20px;
            }
            
            #suitecrm-chat-widget.bottom-left {
                bottom: 20px;
                left: 20px;
            }
            
            .chat-button {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: #3498db;
                color: white;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                transition: all 0.3s ease;
                position: relative;
            }
            
            .chat-button:hover {
                transform: scale(1.05);
                box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
            }
            
            .chat-widget.open .chat-button {
                transform: scale(0);
                opacity: 0;
            }
            
            .chat-icon {
                width: 28px;
                height: 28px;
            }
            
            .chat-button-text {
                display: none;
            }
            
            .chat-notification {
                position: absolute;
                top: -5px;
                right: -5px;
                background: #e74c3c;
                color: white;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: bold;
            }
            
            .chat-container {
                position: absolute;
                bottom: 80px;
                right: 0;
                width: 380px;
                height: 500px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
                display: flex;
                flex-direction: column;
                overflow: hidden;
                animation: slideUp 0.3s ease;
            }
            
            .chat-widget.bottom-left .chat-container {
                right: auto;
                left: 0;
            }
            
            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .chat-header {
                background: #3498db;
                color: white;
                padding: 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .chat-header h3 {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
            }
            
            .chat-header p {
                margin: 4px 0 0;
                font-size: 12px;
                opacity: 0.9;
            }
            
            .chat-close {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                padding: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 4px;
                transition: background 0.2s;
            }
            
            .chat-close:hover {
                background: rgba(255, 255, 255, 0.2);
            }
            
            .chat-messages {
                flex: 1;
                overflow-y: auto;
                padding: 16px;
                background: #f5f5f5;
            }
            
            .chat-message {
                margin-bottom: 16px;
                display: flex;
                flex-direction: column;
            }
            
            .chat-message.user {
                align-items: flex-end;
            }
            
            .chat-message.bot {
                align-items: flex-start;
            }
            
            .chat-message.system {
                align-items: center;
                margin: 20px 0;
            }
            
            .message-bubble {
                max-width: 70%;
                padding: 10px 14px;
                border-radius: 18px;
                word-wrap: break-word;
            }
            
            .chat-message.user .message-bubble {
                background: #3498db;
                color: white;
                border-bottom-right-radius: 4px;
            }
            
            .chat-message.bot .message-bubble {
                background: white;
                color: #333;
                border-bottom-left-radius: 4px;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            }
            
            .chat-message.system .message-bubble {
                background: none;
                color: #666;
                font-size: 12px;
                padding: 8px 12px;
                text-align: center;
                font-style: italic;
            }
            
            .message-time {
                font-size: 11px;
                color: #999;
                margin-top: 4px;
                padding: 0 4px;
            }
            
            .typing-indicator .message-bubble {
                display: flex;
                align-items: center;
                padding: 14px;
            }
            
            .typing-indicator span {
                display: inline-block;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #999;
                margin: 0 2px;
                animation: typing 1.4s infinite;
            }
            
            .typing-indicator span:nth-child(2) {
                animation-delay: 0.2s;
            }
            
            .typing-indicator span:nth-child(3) {
                animation-delay: 0.4s;
            }
            
            @keyframes typing {
                0%, 60%, 100% {
                    transform: translateY(0);
                }
                30% {
                    transform: translateY(-10px);
                }
            }
            
            .chat-input-area {
                padding: 12px;
                background: white;
                border-top: 1px solid #e0e0e0;
            }
            
            .chat-form {
                display: flex;
                gap: 8px;
            }
            
            .chat-input {
                flex: 1;
                padding: 10px 14px;
                border: 1px solid #e0e0e0;
                border-radius: 24px;
                font-size: 14px;
                outline: none;
                transition: border-color 0.2s;
            }
            
            .chat-input:focus {
                border-color: #3498db;
            }
            
            .chat-send {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #3498db;
                color: white;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.2s;
            }
            
            .chat-send:hover {
                background: #2980b9;
            }
            
            .chat-send:active {
                transform: scale(0.95);
            }
            
            /* Dark theme */
            .chat-widget.dark .chat-container {
                background: #2c3e50;
            }
            
            .chat-widget.dark .chat-header {
                background: #34495e;
            }
            
            .chat-widget.dark .chat-messages {
                background: #2c3e50;
            }
            
            .chat-widget.dark .chat-message.bot .message-bubble {
                background: #34495e;
                color: white;
            }
            
            .chat-widget.dark .chat-input-area {
                background: #34495e;
                border-top-color: #4a5f7a;
            }
            
            .chat-widget.dark .chat-input {
                background: #2c3e50;
                border-color: #4a5f7a;
                color: white;
            }
            
            /* Mobile responsiveness */
            @media (max-width: 480px) {
                .chat-container {
                    width: 100vw;
                    height: 100vh;
                    bottom: 0;
                    right: 0;
                    left: 0;
                    border-radius: 0;
                    max-width: none;
                }
                
                .chat-widget.open .chat-button {
                    display: none;
                }
            }
        `;
        
        document.head.appendChild(style);
    }
    
    // Public API
    window.SuiteCRMChat = {
        open: function() {
            if (!isOpen) toggleChat();
        },
        close: function() {
            if (isOpen) toggleChat();
        },
        sendMessage: function(message) {
            addMessage('user', message);
            handleSubmit({ preventDefault: () => {}, target: { querySelector: () => ({ value: message }) } });
        }
    };
    
})();