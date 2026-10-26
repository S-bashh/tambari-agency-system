<?php
declare(strict_types=1);
/**
 * Emergency password reset, for when every administrator is locked out.
 * Run from a Command Prompt on the server computer:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\teams\tools\reset-password.php admin NewPassword1
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
if (!is_file(TEAMS_ROOT . '/config/config.php')) {
    fwrite(STDERR, "TEAMS is not installed yet.\n");
    exit(1);
}
[$script, $username, $password] = array_pad($argv, 3, '');
if ($username === '' || $password === '') {
    fwrite(STDERR, "Usage: php reset-password.php <username> <new-password>\n");
    exit(1);
}
if ($err = Auth::validPassword($password)) {
    fwrite(STDERR, $err . "\n");
    exit(1);
}
$u = db_row('SELECT id, username FROM users WHERE username = ?', [$username]);
if (!$u) {
    fwrite(STDERR, "No user called \"$username\".\n");
    exit(1);
}
db_exec('UPDATE users SET password_hash = ?, must_change_password = 1, is_active = 1, failed_attempts = 0, locked_until = NULL WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int)$u['id']]);
Audit::log('password_reset', 'users', $u['id'], "Password reset from the command line for {$u['username']}", null, (int)$u['id'], 'cli');
echo "Done. {$u['username']} can now sign in with the new password and will be asked to choose another.\n";
