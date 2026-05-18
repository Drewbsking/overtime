<?php
require_once __DIR__ . '/../bootstrap.php';

$token = $_POST['token'] ?? ($_GET['token'] ?? '');
$reset = $token ? Auth::getValidPasswordReset($token) : null;
$error = null;

if (is_post()) {
    validate_csrf();
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$reset) {
        $error = 'This password reset link is invalid or has expired.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            if (Auth::resetPasswordWithToken($token, $new)) {
                if (Auth::check() && (int)Auth::user()['id'] === (int)$reset['user_id']) {
                    Auth::refreshUser((int)$reset['user_id']);
                }
                flash('success', 'Password updated. You can sign in with your new password.');
                redirect('/login.php');
            }

            $error = 'This password reset link is invalid or has expired.';
        } catch (Throwable $e) {
            error_log('Password reset failed: ' . $e->getMessage());
            $error = 'Password could not be updated. Try requesting a new reset link.';
        }
    }
}

$pageTitle = 'Reset Password';
include __DIR__ . '/../templates/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Reset Password</h1>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php endif; ?>
                <?php if (!$reset): ?>
                    <div class="alert alert-warning">This password reset link is invalid or has expired.</div>
                    <a class="btn btn-primary" href="/forgot-password.php">Request New Link</a>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="_token" value="<?php echo h(csrf_token()); ?>">
                        <input type="hidden" name="token" value="<?php echo h($token); ?>">
                        <div class="mb-3">
                            <label class="form-label" for="new_password">New Password</label>
                            <input class="form-control" type="password" name="new_password" id="new_password" required autocomplete="new-password">
                            <div class="form-text">Minimum 8 characters.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="confirm_password">Confirm New Password</label>
                            <input class="form-control" type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password">
                        </div>
                        <button class="btn btn-primary" type="submit">Update Password</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
