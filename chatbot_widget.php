<?php
// chatbot_widget.php — include this file just before </body> on any page.
// Requires csrf_field()/csrf_token() to be available, i.e. config.php (or
// csrf.php) must already be loaded on the page that includes this file.
?>
<style>
    #chatToggleBtn {
        position: fixed; bottom: 24px; right: 24px; width: 60px; height: 60px;
        border-radius: 50%; background: #d4af37; color: #0a0f1e; border: none;
        box-shadow: 0 8px 24px rgba(212,175,55,0.25); cursor: pointer; z-index: 9998;
        display: flex; align-items: center; justify-content: center; font-size: 26px;
        transition: transform .25s ease, box-shadow .25s ease;
    }
    #chatToggleBtn:hover { transform: scale(1.08) rotate(-4deg); box-shadow: 0 10px 30px rgba(212,175,55,0.35); }

    #chatPanel {
        position: fixed; bottom: 96px; right: 24px; width: 340px; max-width: calc(100vw - 32px);
        height: 460px; max-height: calc(100vh - 140px);
        background: rgba(13, 18, 33, 0.92); backdrop-filter: blur(20px);
        border: 1px solid rgba(212,175,55,0.1);
        border-radius: 18px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5); z-index: 9999; display: none;
        flex-direction: column; overflow: hidden; font-family: 'Inter', Arial, sans-serif;
    }
    #chatPanel.open {
        display: flex;
        animation: panelIn 0.25s ease forwards;
    }
    @keyframes panelIn {
        from { opacity: 0; transform: translateY(16px) scale(0.97); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    #chatHeader {
        background: linear-gradient(135deg, #0a0f1e 0%, #12192c 100%);
        border-bottom: 1px solid rgba(212,175,55,0.08);
        color: #fff;
        padding: 16px 18px; display: flex; justify-content: space-between; align-items: center;
    }
    #chatHeader div.title { font-weight: 700; font-size: 15px; color: #fff; }
    #chatHeader div.subtitle { font-size: 11.5px; color: rgba(255,255,255,0.4); margin-top: 2px; }
    #chatCloseBtn { background: none; border: none; color: rgba(255,255,255,0.4); font-size: 20px; cursor: pointer; line-height: 1; transition: color 0.2s ease; }
    #chatCloseBtn:hover { color: #d4af37; }

    #chatMessages { flex: 1; overflow-y: auto; padding: 16px; background: transparent; }
    #chatMessages::-webkit-scrollbar { width: 6px; }
    #chatMessages::-webkit-scrollbar-thumb { background: rgba(212,175,55,0.25); border-radius: 6px; }

    .chat-msg { max-width: 85%; padding: 10px 14px; border-radius: 14px; margin-bottom: 10px; font-size: 13.5px; line-height: 1.45; word-wrap: break-word; animation: msgIn 0.25s ease; }
    @keyframes msgIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
    .chat-msg.bot { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.85); border-bottom-left-radius: 4px; border: 1px solid rgba(255,255,255,0.04); }
    .chat-msg.user { background: #d4af37; color: #0a0f1e; margin-left: auto; border-bottom-right-radius: 4px; font-weight: 500; }
    .chat-msg.typing { color: rgba(255,255,255,0.35); font-style: italic; background: transparent; border: none; padding-left: 0; }

    #chatInputRow { display: flex; gap: 8px; padding: 12px; border-top: 1px solid rgba(212,175,55,0.08); background: rgba(10,15,30,0.4); }
    #chatInput {
        flex: 1; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 10px 12px;
        font-size: 13.5px; font-family: inherit; resize: none;
        background: rgba(255,255,255,0.03); color: #fff;
    }
    #chatInput::placeholder { color: rgba(255,255,255,0.25); }
    #chatInput:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 3px rgba(212,175,55,0.08); }
    #chatSendBtn { background: #d4af37; color: #0a0f1e; border: none; border-radius: 10px; padding: 0 16px; font-weight: 700; cursor: pointer; font-size: 13px; transition: all 0.25s ease; }
    #chatSendBtn:hover { background: #b8922e; transform: translateY(-1px); }
    #chatSendBtn:disabled { opacity: .4; cursor: not-allowed; transform: none; }

    /* NEW: subtle attention pulse on the toggle button, matching the
       WhatsApp float button used elsewhere on the site */
    #chatToggleBtn::before {
        content: ''; position: absolute; inset: 0; border-radius: 50%;
        background: #d4af37; opacity: 0.5; z-index: -1;
        animation: chatPulse 2.6s ease-out infinite;
    }
    @keyframes chatPulse { 0% { transform: scale(1); opacity: 0.4; } 100% { transform: scale(1.4); opacity: 0; } }
    @media (prefers-reduced-motion: reduce) { #chatToggleBtn::before { animation: none; } }

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