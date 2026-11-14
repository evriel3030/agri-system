<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        try {
            $error = attempt_login($username, $password);
        } catch (PDOException $ex) {
            error_log('[AgriSystem] Login DB error: ' . $ex->getMessage());
            $error = 'The system cannot reach the database right now. Please try again later.';
        }
        if ($error === null) {
            flash('success', 'Welcome back, ' . current_user()['full_name'] . '!');
            redirect('dashboard.php');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Agriculture Information System</title>
    <link rel="stylesheet" href="<?= e(url("assets/bootstrap/bootstrap/bootstrap.min.css")) ?>">
    <link rel="stylesheet" href="<?= e(url("assets/bootstrap/bootstrap-icons/bootstrap-icons.min.css")) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="login-wrap">
    <div class="card login-card shadow">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <i class="bi bi-tree-fill text-success" style="font-size:2.5rem"></i>
                <h1 class="h4 mt-2 mb-0">Agriculture Information System</h1>
                <div class="text-muted small">Cebu Technological University</div>
            </div>

            <?php foreach (take_flash() as $m): ?>
                <div class="alert alert-<?= e($m['type']) ?> py-2" role="alert"><?= e($m['message']) ?></div>
            <?php endforeach; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?= e($username) ?>" maxlength="50" autocomplete="username" required autofocus>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password"
                           maxlength="100" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-success w-100"><i class="bi bi-box-arrow-in-right"></i> Log In</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
