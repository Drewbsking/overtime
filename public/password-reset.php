<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$error = null;
$success = null;

if (is_post()) {
    validate_csrf();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $stmt = DB::conn()->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => Auth::user()['id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            Auth::setPassword(Auth::user()['id'], $new, false);
            Auth::refreshUser(Auth::user()['id']);
            $success = 'Password updated.';
        }
    }
}

$pageTitle = 'Set New Password';
include __DIR__ . '/../templates/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Set New Password</h1>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success"><?php echo h($success); ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="_token" value="<?php echo h(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current Password</label>
                        <input class="form-control" type="password" name="current_password" id="current_password" required autocomplete="current-password">
                    </div>
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
                    <?php if (!$success && !empty($_GET['first'])): ?>
                        <span class="text-muted ms-2">Required on first login</span>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
