<?php
// chatbot_widget.php — include this file just before </body> on any page.
// Requires csrf_field()/csrf_token() to be available, i.e. config.php (or
// csrf.php) must already be loaded on the page that includes this file.
?>
<style>
    #chatToggleBtn {
        position: fixed; bottom: 24px; right: 24px; width: 60px; height: 60px;
        border-radius: 50%; background: #d4af37; color: #0f172a; border: none;
        box-shadow: 0 8px 20px rgba(15,23,42,0.25); cursor: pointer; z-index: 9998;
        display: flex; align-items: center; justify-content: center; font-size: 26px;
        transition: transform .2s ease;
    }
    #chatToggleBtn:hover { transform: scale(1.06); }

    #chatPanel {
        position: fixed; bottom: 96px; right: 24px; width: 340px; max-width: calc(100vw - 32px);
        height: 460px; max-height: calc(100vh - 140px); background: #fff; border-radius: 18px;
        box-shadow: 0 20px 45px rgba(15,23,42,0.25); z-index: 9999; display: none;
        flex-direction: column; overflow: hidden; font-family: 'Inter', Arial, sans-serif;
    }
    #chatPanel.open { display: flex; }
    #chatHeader {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff;
        padding: 16px 18px; display: flex; justify-content: space-between; align-items: center;
    }
    #chatHeader div.title { font-weight: 700; font-size: 15px; }
    #chatHeader div.subtitle { font-size: 11.5px; color: #94a3b8; margin-top: 2px; }
    #chatCloseBtn { background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; line-height: 1; }
    #chatCloseBtn:hover { color: #fff; }

    #chatMessages { flex: 1; overflow-y: auto; padding: 16px; background: #f8fafc; }
    .chat-msg { max-width: 85%; padding: 10px 14px; border-radius: 14px; margin-bottom: 10px; font-size: 13.5px; line-height: 1.45; word-wrap: break-word; }
    .chat-msg.bot { background: #eef1f5; color: #0f172a; border-bottom-left-radius: 4px; }
    .chat-msg.user { background: #d4af37; color: #0f172a; margin-left: auto; border-bottom-right-radius: 4px; }
    .chat-msg.typing { color: #94a3b8; font-style: italic; }

    #chatInputRow { display: flex; gap: 8px; padding: 12px; border-top: 1px solid #eef1f5; background: #fff; }
    #chatInput { flex: 1; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; font-size: 13.5px; font-family: inherit; resize: none; }
    #chatInput:focus { outline: none; border-color: #d4af37; }
    #chatSendBtn { background: #0f172a; color: #fff; border: none; border-radius: 10px; padding: 0 16px; font-weight: 600; cursor: pointer; font-size: 13px; }
    #chatSendBtn:hover { background: #d4af37; color: #0f172a; }
    #chatSendBtn:disabled { opacity: .5; cursor: not-allowed; }

    @media (max-width: 480px) {
        #chatPanel { right: 16px; bottom: 88px; width: calc(100vw - 32px); }
        #chatToggleBtn { right: 16px; bottom: 16px; }
    }
</style>

<button id="chatToggleBtn" onclick="toggleChat()" aria-label="Open chat assistant">💬</button>

<div id="chatPanel">
    <div id="chatHeader">
        <div>
            <div class="title">Ahmed Travels Assistant</div>
            <div class="subtitle">Ask about hotels, taxis, or visas</div>
        </div>
        <button id="chatCloseBtn" onclick="toggleChat()" aria-label="Close chat">×</button>
    </div>
    <div id="chatMessages">
        <div class="chat-msg bot">Assalam-o-Alaikum! 👋 Main Ahmed Travels ka assistant hoon. Hotels, taxi, ya visa services ke baare mein kuch bhi puchh sakte hain.</div>
    </div>
    <div id="chatInputRow">
        <textarea id="chatInput" rows="1" placeholder="Type your question..." maxlength="600"></textarea>
        <button id="chatSendBtn" onclick="sendChatMessage()">Send</button>
    </div>
</div>

<script>
const CHAT_CSRF_TOKEN = '<?php echo csrf_token(); ?>';
let chatHistory = [];
let chatOpen = false;

function toggleChat() {
    chatOpen = !chatOpen;
    document.getElementById('chatPanel').classList.toggle('open', chatOpen);
    if(chatOpen) document.getElementById('chatInput').focus();
}

function appendMessage(text, sender) {
    const box = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = 'chat-msg ' + sender;
    div.textContent = text;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
    return div;
}

function sendChatMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if(!message) return;

    const sendBtn = document.getElementById('chatSendBtn');
    appendMessage(message, 'user');
    chatHistory.push({ role: 'user', content: message });
    input.value = '';
    sendBtn.disabled = true;

    const typingEl = appendMessage('Typing...', 'bot typing');

    fetch('chatbot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            message: message,
            history: chatHistory,
            csrf_token: CHAT_CSRF_TOKEN
        })
    })
    .then(res => res.json())
    .then(data => {
        typingEl.remove();
        const reply = data.reply || 'Sorry, something went wrong. Please try again.';
        appendMessage(reply, 'bot');
        chatHistory.push({ role: 'assistant', content: reply });
    })
    .catch(() => {
        typingEl.remove();
        appendMessage('Connection error. Please check your internet and try again.', 'bot');
    })
    .finally(() => {
        sendBtn.disabled = false;
    });
}

document.getElementById('chatInput').addEventListener('keydown', function(e) {
    if(e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendChatMessage();
    }
});
</script>