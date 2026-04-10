<?php
/**
 * Diagnostic page to show the exact redirect URI being used
 */
require_once __DIR__ . '/../config.php';

$base_url = defined('BASE_URL') ? BASE_URL : '/printflow';
$redirect_uri = $base_url . '/public/google-auth.php';

// Build full redirect URI
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$redirect_uri_full = $scheme . '://' . $host . $redirect_uri;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google OAuth Redirect URI Test</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .uri-box {
            background: #f0f9ff;
            border: 2px solid #0ea5e9;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 16px;
            word-break: break-all;
            margin: 20px 0;
        }
        .instructions {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
        }
        .instructions h3 {
            margin-top: 0;
            color: #92400e;
        }
        .instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        .instructions li {
            margin: 8px 0;
        }
        code {
            background: #e5e7eb;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .success {
            color: #059669;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔍 Google OAuth Redirect URI Diagnostic</h1>
        
        <p>Your application is using this redirect URI:</p>
        
        <div class="uri-box">
            <?php echo htmlspecialchars($redirect_uri_full); ?>
        </div>
        
        <div class="instructions">
            <h3>⚠️ How to Fix the Error</h3>
            <ol>
                <li>Go to <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console - Credentials</a></li>
                <li>Click on your <strong>OAuth 2.0 Client ID</strong></li>
                <li>Under <strong>"Authorized redirect URIs"</strong>, click <strong>"+ ADD URI"</strong></li>
                <li>Paste this exact URI (copy from the blue box above):
                    <br><code><?php echo htmlspecialchars($redirect_uri_full); ?></code>
                </li>
                <li>Click <strong>SAVE</strong></li>
                <li>Wait 5 minutes for changes to propagate</li>
                <li>Try signing in with Google again</li>
            </ol>
        </div>
        
        <h3>📋 Additional Information</h3>
        <ul>
            <li><strong>Scheme:</strong> <?php echo $scheme; ?></li>
            <li><strong>Host:</strong> <?php echo $host; ?></li>
            <li><strong>Base URL:</strong> <?php echo $base_url; ?></li>
            <li><strong>Redirect Path:</strong> <?php echo $redirect_uri; ?></li>
        </ul>
        
        <p style="margin-top: 30px; color: #666; font-size: 14px;">
            <strong>Note:</strong> If you're testing locally AND deploying to production, you need to add BOTH URIs to Google Console:
        </p>
        <ul style="font-family: monospace; font-size: 14px;">
            <li>http://localhost/printflow/google-auth/</li>
            <li>https://mrandmrsprintflow.com/google-auth/</li>
        </ul>
    </div>
</body>
</html>
