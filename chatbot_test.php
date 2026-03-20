<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatBot Test</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #00232b;
            color: white;
            height: 300vh;
        }
        
        h1 {
            color: #53C5E0;
            margin-bottom: 30px;
        }
        
        p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        /* ChatBot Styles */
        .lp-chatbot-container {
            position: fixed;
            bottom: 6rem;
            right: 2rem;
            z-index: 50;
            font-family: inherit;
        }

        .lp-chatbot-toggle {
            width: 3.5rem;
            height: 3.5rem;
            background: #53C5E0;
            color: #00232b;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(83, 197, 224, 0.3);
            transition: all .3s;
            font-size: 1.5rem;
        }

        .lp-chatbot-toggle:hover {
            background: #32a1c4;
            box-shadow: 0 6px 20px rgba(50, 161, 196, 0.4);
            transform: scale(1.08);
        }

        .lp-chatbot-toggle svg {
            width: 1.5rem;
            height: 1.5rem;
        }

        .lp-chatbot-window {
            position: absolute;
            bottom: 5rem;
            right: 0;
            width: 380px;
            max-width: 90vw;
            height: 500px;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 5px 40px rgba(0, 0, 0, 0.16);
            display: flex;
            flex-direction: column;
            animation: slideUp .3s ease-out;
        }

        .lp-chatbot-window.lp-chatbot-hidden {
            display: none;
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

        .lp-chatbot-header {
            padding: 1rem;
            background: linear-gradient(135deg, #00232b, #1a5a6f);
            color: #fff;
            border-radius: 1rem 1rem 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .lp-chatbot-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .lp-chatbot-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 1.5rem;
            height: 1.5rem;
            transition: transform .2s;
        }

        .lp-chatbot-close:hover {
            transform: rotate(90deg);
        }

        .lp-chatbot-content {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: white;
        }

        .lp-chatbot-message {
            display: flex;
            margin-bottom: .5rem;
        }

        .lp-chatbot-message.lp-chatbot-bot {
            justify-content: flex-start;
        }

        .lp-chatbot-message.lp-chatbot-bot p {
            background: #f0f0f0;
            color: #333;
            padding: .75rem 1rem;
            border-radius: .75rem;
            max-width: 80%;
            word-wrap: break-word;
            margin: 0;
        }

        .lp-chatbot-message.lp-chatbot-user {
            justify-content: flex-end;
        }

        .lp-chatbot-message.lp-chatbot-user p {
            background: #53C5E0;
            color: #fff;
            padding: .75rem 1rem;
            border-radius: .75rem;
            max-width: 80%;
            word-wrap: break-word;
            margin: 0;
        }

        .lp-chatbot-questions {
            padding: .5rem 1rem;
            border-top: 1px solid #e0e0e0;
            overflow-y: auto;
            max-height: 150px;
            background: white;
        }

        .lp-chatbot-question-btn {
            display: block;
            width: 100%;
            text-align: left;
            padding: .75rem;
            margin: .5rem 0;
            background: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: .5rem;
            cursor: pointer;
            font-size: .875rem;
            color: #333;
            transition: all .2s;
        }

        .lp-chatbot-question-btn:hover {
            background: #e8f4f8;
            border-color: #53C5E0;
            color: #00232b;
        }

        .lp-chatbot-question-btn.lp-chatbot-active {
            background: #e8f4f8;
            border-color: #53C5E0;
            font-weight: 600;
        }

        @media (max-width: 480px) {
            .lp-chatbot-toggle {
                bottom: 1rem;
                right: 1rem;
            }
            .lp-chatbot-window {
                width: calc(100vw - 2rem);
                bottom: 5.5rem;
                right: -0.5rem;
            }
        }
    </style>
</head>
<body>
    <h1>🧪 Support chat test page</h1>
    <p>Scroll down and look for the <strong>cyan chat bubble button</strong> in the bottom-right corner of your screen.</p>
    
    <p>This is a test page to verify the support chat widget displays correctly. The chat button should appear as a <strong>bright cyan/teal circle</strong> with a chat icon inside it.</p>
    
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
    <p>Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>
    <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>
    
    <!-- ChatBot Widget -->
    <div class="lp-chatbot-container" id="lp-chatbot-container">
        <button class="lp-chatbot-toggle" id="lp-chatbot-toggle" aria-label="Open chat">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        </button>
        <div class="lp-chatbot-window lp-chatbot-hidden" id="lp-chatbot-window">
            <div class="lp-chatbot-header">
                <h3>Support chat</h3>
                <button class="lp-chatbot-close" id="lp-chatbot-close">×</button>
            </div>
            <div class="lp-chatbot-content" id="lp-chatbot-content">
                <div class="lp-chatbot-message lp-chatbot-bot">
                    <p>Hello! 👋 How can we help you today?</p>
                </div>
            </div>
            <div class="lp-chatbot-questions" id="lp-chatbot-questions">
                <button class="lp-chatbot-question-btn" data-question="What is PrintFlow?" data-answer="PrintFlow is your trusted online printing shop offering high-quality custom printing services.">What is PrintFlow?</button>
                <button class="lp-chatbot-question-btn" data-question="Where are you located?" data-answer="We are located in the heart of the business district. Contact us for exact directions.">Where are you located?</button>
                <button class="lp-chatbot-question-btn" data-question="How can I place an order?" data-answer="Sign up for a free account, browse our products, and place your order easily!">How can I place an order?</button>
            </div>
        </div>
    </div>

    <script>
        var chatbotToggle = document.getElementById('lp-chatbot-toggle');
        var chatbotWindow = document.getElementById('lp-chatbot-window');
        var chatbotClose = document.getElementById('lp-chatbot-close');
        var chatbotContent = document.getElementById('lp-chatbot-content');

        chatbotToggle.addEventListener('click', function(e) {
            e.preventDefault();
            chatbotWindow.classList.toggle('lp-chatbot-hidden');
            console.log('ChatBot toggled');
        });

        chatbotClose.addEventListener('click', function(e) {
            e.preventDefault();
            chatbotWindow.classList.add('lp-chatbot-hidden');
        });

        document.querySelectorAll('.lp-chatbot-question-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var userMsg = document.createElement('div');
                userMsg.className = 'lp-chatbot-message lp-chatbot-user';
                userMsg.innerHTML = '<p>' + this.dataset.question + '</p>';
                chatbotContent.appendChild(userMsg);

                setTimeout(() => {
                    var botMsg = document.createElement('div');
                    botMsg.className = 'lp-chatbot-message lp-chatbot-bot';
                    botMsg.innerHTML = '<p>' + this.dataset.answer + '</p>';
                    chatbotContent.appendChild(botMsg);
                    chatbotContent.scrollTop = chatbotContent.scrollHeight;
                }, 300);
            });
        });

        console.log('ChatBot initialized');
    </script>
</body>
</html>
