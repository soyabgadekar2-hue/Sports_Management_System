<?php

require_once __DIR__ . '/../includes/header.php';

$assistantUser = currentUser();
$assistantRole = (string) ($assistantUser['role'] ?? 'PLAYER');

if (empty($_SESSION['ai_assistant_csrf'])) {
    $_SESSION['ai_assistant_csrf'] = bin2hex(random_bytes(32));
}

$assistantSuggestions = match ($assistantRole) {
    'ADMIN' => [
        'How do I approve a student?',
        'How do I manage teams?',
        'How do I manage tournaments?',
    ],
    'SPORTS_COORDINATOR' => [
        'How do I schedule a match?',
        'How do I enter match results?',
        'How do I view tournament standings?',
    ],
    'COACH' => [
        'How do I view my team statistics?',
        'How do I find my players?',
        'How do I check my matches?',
    ],
    default => [
        'How do I view my upcoming matches?',
        'How do I find my team?',
        'Where can I view my statistics?',
    ],
};

?>

<style>
    .ai-assistant-page {
        max-width: 1100px;
        margin: 0 auto;
        padding: 24px;
        color: #172033;
    }

    .ai-assistant-heading {
        margin-bottom: 22px;
    }

    .ai-assistant-heading h1 {
        margin: 0 0 8px;
        font-size: clamp(24px, 3vw, 32px);
        font-weight: 800;
    }

    .ai-assistant-heading p {
        margin: 0;
        color: #64748b;
        line-height: 1.6;
    }

    .ai-chat-card {
        display: flex;
        flex-direction: column;
        min-height: 620px;
        overflow: hidden;
        background: #fff;
        border: 1px solid #e5eaf2;
        border-radius: 18px;
        box-shadow: 0 12px 35px rgba(15, 23, 42, 0.06);
    }

    .ai-chat-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 20px 24px;
        border-bottom: 1px solid #e8edf5;
    }

    .ai-chat-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        color: #fff;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        border-radius: 15px;
        font-size: 23px;
    }

    .ai-chat-heading {
        min-width: 0;
        flex: 1;
    }

    .ai-chat-heading h2 {
        margin: 0 0 5px;
        font-size: 17px;
        font-weight: 800;
    }

    .ai-chat-heading p {
        margin: 0;
        color: #64748b;
        font-size: 13px;
    }

    .ai-online-status {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: #15803d;
        font-size: 12px;
        font-weight: 700;
    }

    .ai-online-dot {
        width: 8px;
        height: 8px;
        background: #22c55e;
        border-radius: 50%;
    }

    .ai-chat-messages {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 18px;
        min-height: 300px;
        max-height: 520px;
        overflow-y: auto;
        padding: 24px;
        background: #f8fafc;
    }

    .ai-message {
        display: flex;
        gap: 10px;
        max-width: 85%;
        animation: aiMessageAppear 0.2s ease-out;
    }

    .ai-message.user {
        align-self: flex-end;
        flex-direction: row-reverse;
    }

    .ai-message-avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        color: #1d4ed8;
        background: #dbeafe;
        border-radius: 50%;
        font-size: 14px;
        font-weight: 800;
    }

    .ai-message.user .ai-message-avatar {
        color: #fff;
        background: #2563eb;
    }

    .ai-message-content {
        min-width: 0;
    }

    .ai-message-label {
        margin: 0 0 6px;
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
    }

    .ai-message-bubble {
        padding: 13px 16px;
        color: #263247;
        background: #fff;
        border: 1px solid #e5eaf2;
        border-radius: 4px 16px 16px 16px;
        font-size: 14px;
        line-height: 1.65;
        overflow-wrap: anywhere;
        white-space: pre-wrap;
    }

    .ai-message.user .ai-message-bubble {
        color: #fff;
        background: #2563eb;
        border-color: #2563eb;
        border-radius: 16px 4px 16px 16px;
    }

    .ai-suggestions {
        padding: 18px 24px 8px;
        background: #fff;
    }

    .ai-suggestions-title {
        margin: 0 0 12px;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .ai-suggestions-list {
        display: flex;
        flex-wrap: wrap;
        gap: 9px;
    }

    .ai-suggestion-button {
        padding: 9px 13px;
        color: #1d4ed8;
        background: #eff6ff;
        border: 1px solid #dbeafe;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.2s ease;
    }

    .ai-suggestion-button:hover {
        background: #dbeafe;
        transform: translateY(-1px);
    }

    .ai-chat-form {
        display: flex;
        align-items: flex-end;
        gap: 12px;
        padding: 18px 24px 22px;
        background: #fff;
    }

    .ai-chat-input {
        width: 100%;
        min-height: 48px;
        max-height: 140px;
        resize: vertical;
        padding: 13px 15px;
        color: #172033;
        background: #fff;
        border: 1px solid #dbe2ec;
        border-radius: 13px;
        font: inherit;
        font-size: 14px;
        outline: none;
    }

    .ai-chat-input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .ai-send-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        color: #fff;
        background: #2563eb;
        border: 0;
        border-radius: 13px;
        font-size: 20px;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.2s ease;
    }

    .ai-send-button:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
    }

    .ai-send-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .ai-chat-note {
        margin: 0;
        padding: 0 24px 18px;
        color: #94a3b8;
        background: #fff;
        font-size: 11px;
        text-align: center;
    }

    @keyframes aiMessageAppear {
        from {
            opacity: 0;
            transform: translateY(5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 700px) {
        .ai-assistant-page {
            padding: 16px 12px;
        }

        .ai-chat-card {
            min-height: 70vh;
            border-radius: 14px;
        }

        .ai-chat-header {
            padding: 16px;
        }

        .ai-chat-messages {
            padding: 18px 14px;
        }

        .ai-message {
            max-width: 95%;
        }

        .ai-suggestions {
            padding: 16px 14px 8px;
        }

        .ai-chat-form {
            padding: 14px;
        }

        .ai-chat-note {
            padding: 0 14px 16px;
        }

        .ai-online-status {
            font-size: 0;
        }

        .ai-online-dot {
            width: 9px;
            height: 9px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .ai-message,
        .ai-suggestion-button,
        .ai-send-button {
            animation: none;
            transition: none;
        }
    }
</style>

<section class="ai-assistant-page">
    <div class="ai-assistant-heading">
        <h1>AI Assistant</h1>
        <p>Your SportSync helper for sports management and system guidance.</p>
    </div>

    <section class="ai-chat-card" aria-label="SportSync AI Assistant">
        <header class="ai-chat-header">
            <div class="ai-chat-logo" aria-hidden="true">✦</div>

            <div class="ai-chat-heading">
                <h2>SportSync Assistant</h2>
                <p>Ask questions about using SportSync</p>
            </div>

            <div class="ai-online-status">
                <span class="ai-online-dot"></span>
                PHP demo
            </div>
        </header>

        <div
            class="ai-chat-messages"
            id="aiChatMessages"
            aria-live="polite"
            aria-label="Chat messages"
        >
            <div class="ai-message">
                <div class="ai-message-avatar" aria-hidden="true">✦</div>
                <div class="ai-message-content">
                    <p class="ai-message-label">SportSync Assistant</p>
                    <div class="ai-message-bubble">
                        Hello! I can help you understand how to use SportSync.
                        Choose a suggested question or type your own below.
                    </div>
                </div>
            </div>
        </div>

        <div class="ai-suggestions">
            <p class="ai-suggestions-title">Suggested questions</p>

            <div class="ai-suggestions-list" id="aiSuggestions">
                <?php foreach ($assistantSuggestions as $suggestion): ?>
                    <button
                        type="button"
                        class="ai-suggestion-button"
                        data-question="<?= htmlspecialchars($suggestion, ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <?= htmlspecialchars($suggestion, ENT_QUOTES, 'UTF-8') ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <form class="ai-chat-form" id="aiChatForm">
            <textarea
                class="ai-chat-input"
                id="aiChatInput"
                rows="1"
                maxlength="1000"
                placeholder="Ask SportSync Assistant..."
                aria-label="Type your question"
                required
            ></textarea>

            <button
                type="submit"
                class="ai-send-button"
                id="aiSendButton"
                aria-label="Send message"
            >
                ➤
            </button>
        </form>

        <p class="ai-chat-note" id="aiChatNote">
            PHP demo: answers are sample guidance, not live AI responses.
        </p>
    </section>
</section>

<script>
(() => {
    'use strict';

    const form = document.getElementById('aiChatForm');
    const input = document.getElementById('aiChatInput');
    const messages = document.getElementById('aiChatMessages');
    const sendButton = document.getElementById('aiSendButton');
    const suggestions = document.getElementById('aiSuggestions');
    const note = document.getElementById('aiChatNote');

    const csrfToken = <?= json_encode(
        $_SESSION['ai_assistant_csrf'],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ) ?>;

    if (!form || !input || !messages || !sendButton || !suggestions) {
        return;
    }

    function addMessage(text, sender) {
        const message = document.createElement('div');
        message.className = `ai-message ${sender}`;

        const avatar = document.createElement('div');
        avatar.className = 'ai-message-avatar';
        avatar.setAttribute('aria-hidden', 'true');
        avatar.textContent = sender === 'user' ? 'U' : '✦';

        const content = document.createElement('div');
        content.className = 'ai-message-content';

        const label = document.createElement('p');
        label.className = 'ai-message-label';
        label.textContent = sender === 'user' ? 'You' : 'SportSync Assistant';

        const bubble = document.createElement('div');
        bubble.className = 'ai-message-bubble';
        bubble.textContent = text;

        content.append(label, bubble);
        message.append(avatar, content);
        messages.appendChild(message);
        messages.scrollTop = messages.scrollHeight;

        return message;
    }

    async function sendMessage(question) {
        const cleanQuestion = question.trim();

        if (!cleanQuestion || sendButton.disabled) {
            return;
        }

        addMessage(cleanQuestion, 'user');
        input.value = '';
        sendButton.disabled = true;
        input.disabled = true;

        const loadingMessage = addMessage('Thinking...', 'assistant');

        try {
            const response = await fetch('../api/ai-chat.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    message: cleanQuestion
                })
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'The assistant could not process your question.');
            }

            const bubble = loadingMessage.querySelector('.ai-message-bubble');
            if (bubble) {
                bubble.textContent = result.reply || 'No response was returned.';
            }

            if (note) {
                note.textContent = 'PHP demo: answers are sample guidance, not live AI responses.';
            }
        } catch (error) {
            const bubble = loadingMessage.querySelector('.ai-message-bubble');

            if (bubble) {
                bubble.textContent = error.message || 'Could not connect to the assistant. Please try again.';
            }

            if (note) {
                note.textContent = 'Connection problem: check that you are logged in and the PHP endpoint exists.';
            }
        } finally {
            sendButton.disabled = false;
            input.disabled = false;
            input.focus();
            messages.scrollTop = messages.scrollHeight;
        }
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage(input.value);
    });

    suggestions.addEventListener('click', (event) => {
        const button = event.target.closest('[data-question]');

        if (!button) {
            return;
        }

        sendMessage(button.dataset.question || '');
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>