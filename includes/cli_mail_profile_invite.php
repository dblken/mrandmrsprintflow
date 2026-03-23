<?php
/**
 * CLI-only: send one profile-completion invite (spawned in background from admin).
 * Usage: php cli_mail_profile_invite.php <path-to-json-job-file>
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$path = $argv[1] ?? '';
if ($path === '' || !is_readable($path)) {
    exit(1);
}

$raw = @file_get_contents($path);
@unlink($path);
if ($raw === false || $raw === '') {
    exit(1);
}

$data = json_decode($raw, true);
if (!is_array($data) || empty($data['to']) || empty($data['link'])) {
    exit(1);
}

$to = (string) $data['to'];
$first = (string) ($data['first'] ?? 'User');
$link = (string) $data['link'];

require_once __DIR__ . '/profile_completion_mailer.php';

try {
    $mail_res = send_profile_completion_email($to, $first, $link);
    if (empty($mail_res['success'])) {
        error_log('cli_mail_profile_invite: ' . ($mail_res['message'] ?? 'send failed'));
        exit(1);
    }
} catch (Throwable $e) {
    error_log('cli_mail_profile_invite: ' . $e->getMessage());
    exit(1);
}

exit(0);
