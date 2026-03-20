<?php
/**
 * Email and SMS Configuration
 * PrintFlow - Printing Shop PWA
 * 
 * Configure your email and SMS settings here
 */

// ==========================================
// EMAIL CONFIGURATION (PHPMailer)
// ==========================================

// Email service type: 'smtp', 'mail', or 'sendmail'
define('EMAIL_SERVICE', 'smtp'); // For local development, use 'mail'. For production, use 'smtp'

// SMTP Settings (for production)
define('SMTP_HOST', 'smtp.gmail.com');          // Gmail: smtp.gmail.com, Outlook: smtp-mail.outlook.com
define('SMTP_PORT', 587);                        // 587 for TLS, 465 for SSL
define('SMTP_ENCRYPTION', 'tls');               // 'tls' or 'ssl'
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your email address
define('SMTP_PASSWORD', 'your-app-password');    // Gmail: Use App Password, not account password

// Sender Information
define('EMAIL_FROM_ADDRESS', 'noreply@printflow.com');
define('EMAIL_FROM_NAME', 'PrintFlow');

// Email Settings
define('EMAIL_ENABLED', true); // Set to false to disable email sending (for testing)

// ==========================================
// SMS CONFIGURATION
// ==========================================

// SMS service type: 'semaphore', 'twilio', 'none'
define('SMS_SERVICE', 'none'); // Set to 'none' for development

// Semaphore SMS (Philippines) - https://semaphore.co
define('SEMAPHORE_API_KEY', 'your-semaphore-api-key');
define('SEMAPHORE_SENDER_NAME', 'PrintFlow'); // Max 11 characters

// Twilio SMS (International) - https://www.twilio.com
define('TWILIO_ACCOUNT_SID', 'your-twilio-account-sid');
define('TWILIO_AUTH_TOKEN', 'your-twilio-auth-token');
define('TWILIO_PHONE_NUMBER', '+1234567890'); // Your Twilio phone number

// SMS Settings
define('SMS_ENABLED', false); // Set to true when SMS is configured

// ==========================================
// PHONE VERIFICATION (APILayer NumVerify)
// ==========================================
// For Philippine mobile validation. Get key: https://apilayer.com/marketplace/number_verification-api
define('APILAYER_NUMBER_VERIFICATION_API_KEY', getenv('APILAYER_NUMBER_KEY') ?: 'tYQvyTsrmK5ZJ90eVGL1EMFz1y3YKG1U');

// ==========================================
// ENVIRONMENT & DEBUGGING
// ==========================================

// Set to 'production' when deploying to live server
define('APP_ENVIRONMENT', 'development'); // 'development' or 'production'

// --- IMPORTANT FOR TESTING ---
// Set to 'true' to ALWAYS show debug info (like reset codes) in the API response,
// regardless of APP_ENVIRONMENT. This is useful for testing email functionality
// without receiving actual emails.
define('FORCE_DEBUG_MODE', true); 

// Development mode shows debug info (like reset codes in response)
define('DEBUG_MODE', APP_ENVIRONMENT === 'development' || FORCE_DEBUG_MODE === true);

// ==========================================
// HOW TO CONFIGURE
// ==========================================

/*
 * 
 * !!! IMPORTANT: HOW TO CONFIGURE GMAIL FOR TESTING !!!
 * 
 * 1. Set EMAIL_SERVICE to 'smtp'.
 * 
 * 2. Enable 2-Factor Authentication in your Google Account.
 * 
 * 3. Go to: https://myaccount.google.com/apppasswords
 *    - Select App: "Mail"
 *    - Select Device: "Other (Custom name)" -> name it "PrintFlow"
 *    - Click "Generate".
 * 
 * 4. Copy the 16-character password that appears.
 * 
 * 5. Update the credentials below:
 *    - SMTP_USERNAME: Your full Gmail address (e.g., 'your.name@gmail.com')
 *    - SMTP_PASSWORD: The 16-character App Password you just generated (e.g., 'abcd efgh ijkl mnop')
 * 
 * 6. To disable receiving the reset code in the API response for production,
 *    change FORCE_DEBUG_MODE to 'false'.
 * 
 */

/*
 * FOR GMAIL (Recommended for small scale):
 * 
 * 1. Enable 2-Factor Authentication in your Google Account
 * 2. Go to: https://myaccount.google.com/apppasswords
 * 3. Generate an App Password for "Mail"
 * 4. Use that 16-character password as SMTP_PASSWORD
 * 5. Set EMAIL_SERVICE to 'smtp'
 * 6. Update SMTP_USERNAME with your Gmail address
 * 
 * Example:
 * define('EMAIL_SERVICE', 'smtp');
 * define('SMTP_USERNAME', 'yourname@gmail.com');
 * define('SMTP_PASSWORD', 'abcd efgh ijkl mnop'); // App Password from Google
 */

/*
 * FOR OUTLOOK/HOTMAIL:
 * 
 * 1. Set SMTP_HOST to 'smtp-mail.outlook.com'
 * 2. Set SMTP_PORT to 587
 * 3. Use your Outlook email and password
 * 4. Enable SMTP in Outlook settings if needed
 */

/*
 * FOR SEMAPHORE SMS (Philippines):
 * 
 * 1. Sign up at https://semaphore.co
 * 2. Get your API key from the dashboard
 * 3. Set SMS_SERVICE to 'semaphore'
 * 4. Update SEMAPHORE_API_KEY
 * 5. Set SMS_ENABLED to true
 */

/*
 * FOR TWILIO SMS (International):
 * 
 * 1. Sign up at https://www.twilio.com
 * 2. Get Account SID and Auth Token from console
 * 3. Get a Twilio phone number
 * 4. Set SMS_SERVICE to 'twilio'
 * 5. Update TWILIO_* constants
 * 6. Set SMS_ENABLED to true
 */

/*
 * FOR PRODUCTION DEPLOYMENT:
 * 
 * 1. Set APP_ENVIRONMENT to 'production'
 * 2. Configure real SMTP credentials
 * 3. Configure SMS service if needed
 * 4. Enable EMAIL_ENABLED and SMS_ENABLED
 * 5. Remove or secure this config file (use environment variables if possible)
 */
