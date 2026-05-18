<?php
require_once __DIR__ . '/../bootstrap.php';

if (Auth::check()) {
    redirect('/dashboard.php');
}

$error = null;

if (is_post()) {
    validate_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (Auth::attempt($username, $password)) {
        if (Auth::mustReset()) {
            redirect('/password-reset.php?first=1');
        }
        redirect('/dashboard.php');
    } else {
        $error = 'Invalid credentials.';
    }
}

$pageTitle = 'Login';
include __DIR__ . '/../templates/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Login</h1>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="_token" value="<?php echo h(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control" type="text" name="username" id="username" required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" type="password" name="password" id="password" required autocomplete="current-password">
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Sign in</button>
                </form>
                <div class="mt-3 text-center">
                    <a href="/forgot-password.php">Forgot password?</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
