<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Chatbot Standalone Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f0f0f0;
            height: 300vh;
        }
        
        .lp-chatbot-container {
            position: fixed;
            bottom: 6rem;
            right: 2rem;
            z-index: 50;
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
            font-size: 1.5rem;
        }

        .lp-chatbot-toggle:hover {
            background: #32a1c4;
            box-shadow: 0 6px 20px rgba(50, 161, 196, 0.4);
        }

        .lp-chatbot-window {
            position: absolute;
            bottom: 5rem;
            right: 0;
            width: 380px;
            height: 500px;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 5px 40px rgba(0, 0, 0, 0.16);
            display: flex;
            flex-direction: column;
        }

        .lp-chatbot-window.lp-chatbot-hidden {
            display: none;
        }

        .lp-chatbot-header {
            padding: 1rem;
            background: linear-gradient(135deg, #00232b, #1a5a6f);
            color: #fff;
            border-radius: 1rem 1rem 0 0;
            display: flex;
            justify-content: space-between;
        }

        .lp-chatbot-header h3 {
            margin: 0;
        }

        .lp-chatbot-close {
            background: none;
            border: none;
            color: #fff;
            cursor: pointer;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <h1>Standalone Chatbot Test</h1>
    <p>Scroll down and look for the cyan button...</p>
    <p style="margin-top: 200px;">Can you see the chat button?</p>

    <!-- CHATBOT WIDGET -->
    <div class="lp-chatbot-container" id="lp-chatbot-container">
        <button class="lp-chatbot-toggle" id="lp-chatbot-toggle">💬</button>
        <div class="lp-chatbot-window lp-chatbot-hidden" id="lp-chatbot-window">
            <div class="lp-chatbot-header">
                <h3>Chat</h3>
                <button class="lp-chatbot-close" id="lp-chatbot-close">×</button>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('lp-chatbot-toggle').addEventListener('click', function() {
            document.getElementById('lp-chatbot-window').classList.toggle('lp-chatbot-hidden');
            console.log('✅ Chatbot toggled!');
        });
        
        document.getElementById('lp-chatbot-close').addEventListener('click', function() {
            document.getElementById('lp-chatbot-window').classList.add('lp-chatbot-hidden');
        });

        console.log('✅ Chatbot script loaded');
    </script>
</body>
</html>
