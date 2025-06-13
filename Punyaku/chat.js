document.addEventListener('DOMContentLoaded', function() {
    const chatBtn = document.querySelector('.chatbot-btn');
    const chatContainer = document.getElementById('chat-container');
    const chatClose = document.querySelector('.chat-close');
    const chatInput = document.getElementById('chat-input');
    const chatSend = document.getElementById('chat-send');
    const chatMessages = document.getElementById('chat-messages');
    
    // Toggle chat window
    chatBtn.addEventListener('click', function() {
        chatContainer.classList.toggle('active');
        if (chatContainer.classList.contains('active')) {
            loadChatHistory();
        }
    });
    
    chatClose.addEventListener('click', function() {
        chatContainer.classList.remove('active');
    });
    
    // Send message
    chatSend.addEventListener('click', sendMessage);
    chatInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
    
    function sendMessage() {
        const message = chatInput.value.trim();
        if (message === '') return;
        
        // Add user message to chat
        addMessage(message, 'user');
        chatInput.value = '';
        
        // Send to server
        fetch('/api/groq.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ message: message })
        })
        .then(response => response.json())
        .then(data => {
            if (data.response) {
                addMessage(data.response, 'bot');
            } else if (data.error) {
                addMessage(data.error, 'bot');
            }
        })
        .catch(error => {
            addMessage('Error connecting to the chatbot service.', 'bot');
        });
    }
    
    function addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}`;
        
        const messageContent = document.createElement('div');
        messageContent.className = 'message-content';
        messageContent.textContent = text;
        
        messageDiv.appendChild(messageContent);
        chatMessages.appendChild(messageDiv);
        
        // Scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    function loadChatHistory() {
        fetch('/api/chat_history.php')
        .then(response => response.json())
        .then(data => {
            chatMessages.innerHTML = '';
            data.forEach(msg => {
                addMessage(msg.message, msg.is_user ? 'user' : 'bot');
            });
        });
    }
});