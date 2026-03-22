<?php
/**
 * Copy to smtp_config.php and fill in real values.
 * Used by: otp_mailer.php, profile_completion_mailer.php, send_email() in functions.php
 *
 * Gmail: enable 2FA → App passwords → generate for "Mail".
 */
return [
    'smtp_host'     => 'smtp.gmail.com',
    'smtp_port'     => 587,
    'smtp_user'     => 'your.address@gmail.com',
    'smtp_pass'     => 'your-16-char-app-password',
    'smtp_secure'   => 'tls',

    'from_email'    => 'your.address@gmail.com',
    'from_name'     => 'PrintFlow',

    'otp_expiry_minutes'   => 5,
    'otp_resend_cooldown'  => 60,
];
