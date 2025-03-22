const { createApp, ref, onMounted, nextTick, watch } = Vue;

createApp({
    setup() {
        const username = ref('');
        const usernameInput = ref('');
        const newMessage = ref('');
        const messages = ref([]);
        const messagesContainer = ref(null);
        let eventSource = null;

        const setUsername = () => {
            if (usernameInput.value.trim()) {
                username.value = usernameInput.value.trim();
                initMercure();
            }
        };

        const initMercure = async () => {
            try {
                // Authenticate with the backend to set the cookie
                await fetch('/api/auth', {
                    method: 'GET',
                    credentials: 'include', // Important: include cookies
                });
                
                // Connect to Mercure hub with credentials (cookies)
                const url = new URL('/.well-known/mercure', window.location.origin);
                url.searchParams.append('topic', 'https://chat.example.com/messages');
                
                eventSource = new EventSource(url, {
                    withCredentials: true // Important: send cookies with the request
                });
                
                eventSource.onmessage = event => {
                    const msg = JSON.parse(event.data);
                    messages.value.push(msg);
                    scrollToBottom();
                };
                
                eventSource.onerror = err => {
                    console.error('EventSource error:', err);
                };
            } catch (error) {
                console.error('Error initializing Mercure:', error);
            }
        };

        const sendMessage = async () => {
            if (!newMessage.value.trim()) return;
            
            try {
                const response = await fetch('/api/messages', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        username: username.value,
                        message: newMessage.value
                    })
                });
                
                if (response.ok) {
                    newMessage.value = '';
                } else {
                    console.error('Failed to send message');
                }
            } catch (error) {
                console.error('Error sending message:', error);
            }
        };

        const scrollToBottom = () => {
            nextTick(() => {
                if (messagesContainer.value) {
                    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
                }
            });
        };

        const formatTime = (timestamp) => {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        };

        watch(messages, scrollToBottom);

        onMounted(() => {
            // Clean up on component unmount
            return () => {
                if (eventSource) {
                    eventSource.close();
                }
            };
        });

        return {
            username,
            usernameInput,
            newMessage,
            messages,
            messagesContainer,
            setUsername,
            sendMessage,
            formatTime
        };
    }
}).mount('#app');
