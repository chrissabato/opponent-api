<?php
/**
 * Make a password hash for an admin account (auth = 'password').
 *
 *   php bin/add_admin.php <username>
 *
 * Prompts for the password and prints a line to paste into 'admin_users' in config.php.
 * To remove an admin, delete their line; to change a password, replace it.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$user = isset($argv[1]) ? trim($argv[1]) : '';
if ($user === '' || !preg_match('/^[A-Za-z0-9._@-]+$/', $user)) {
    fwrite(STDERR, "Usage: php bin/add_admin.php <username>   (letters, digits, . _ @ -)\n");
    exit(1);
}

function prompt_hidden($label)
{
    fwrite(STDERR, $label);
    $tty = DIRECTORY_SEPARATOR === '/' && function_exists('posix_isatty') ? posix_isatty(STDIN) : false;
    if ($tty) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($tty) {
        shell_exec('stty echo');
        fwrite(STDERR, "\n");
    }
    return $line === false ? '' : rtrim($line, "\r\n");
}

$pass = prompt_hidden("Password for $user: ");
if (strlen($pass) < 10) {
    fwrite(STDERR, "Use at least 10 characters.\n");
    exit(1);
}
if (prompt_hidden('Again: ') !== $pass) {
    fwrite(STDERR, "Passwords don't match.\n");
    exit(1);
}

fwrite(STDERR, "\nAdd this line inside 'admin_users' => array( ... ) in config.php:\n\n");
echo "        " . var_export($user, true) . ' => ' . var_export(password_hash($pass, PASSWORD_DEFAULT), true) . ",\n";
