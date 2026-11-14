<?php


function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_user_id(): ?int
{
    return isset($_SESSION['user']['user_id']) ? (int)$_SESSION['user']['user_id'] : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return (current_user()['role_name'] ?? '') === 'Administrator';
}

/** Call at the top of every protected page. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }
}

/** Call on pages/actions that only administrators may use. */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        flash('danger', 'Access denied. This action is for administrators only.');
        redirect('dashboard.php');
    }
}

/**
 * Try to log in. Returns an error message, or null when successful.
 * Includes a simple brute-force limit: 5 failed tries -> wait 60 seconds.
 */
function attempt_login(string $username, string $password): ?string
{
    $lockUntil = $_SESSION['login_lock'] ?? 0;
    if ($lockUntil > time()) {
        return 'Too many failed attempts. Please wait ' . ($lockUntil - time()) . ' seconds and try again.';
    }

    $st = db()->prepare(
        'SELECT u.user_id, u.username, u.password_hash, u.full_name, u.is_active, r.role_name
           FROM app_user u JOIN role r ON r.role_id = u.role_id
          WHERE lower(u.username) = lower(?)'
    );
    $st->execute([$username]);
    $user = $st->fetch();

    // Always run password_verify (even for unknown users) so timing does not reveal valid usernames.
    $hash = $user['password_hash'] ?? '$2y$10$VbLMXNusHe5emASM1MSJcOARlaBYiFMCiM5mIWRW2zVjhwYRm0rpa'; // dummy hash for unknown users
    $ok = password_verify($password, $hash) && $user && $user['is_active'];

    if (!$ok) {
        $_SESSION['login_fail'] = ($_SESSION['login_fail'] ?? 0) + 1;
        if ($_SESSION['login_fail'] >= 5) {
            $_SESSION['login_lock'] = time() + 60;
            $_SESSION['login_fail'] = 0;
        }
        return 'Invalid username or password.';
    }

    session_regenerate_id(true);
    unset($_SESSION['login_fail'], $_SESSION['login_lock']);
    $_SESSION['user'] = [
        'user_id'   => (int)$user['user_id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role_name' => $user['role_name'],
    ];
    return null;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
