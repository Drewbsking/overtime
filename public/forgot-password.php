<?php
require_once __DIR__ . '/../bootstrap.php';

if (Auth::check()) {
    redirect('/password-reset.php');
}

$submitted = false;
$error = null;

if (is_post()) {
    validate_csrf();
    $email = trim($_POST['email'] ?? '');
    $submitted = true;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
        $submitted = false;
    } else {
        try {
            $user = Auth::findActiveUserByEmail($email);
            if ($user) {
                $ttlMinutes = (int)env('PASSWORD_RESET_MINUTES', 60);
                $token = Auth::createPasswordResetToken((int)$user['id'], $ttlMinutes);
                $resetUrl = app_url('reset-password.php?token=' . urlencode($token));
                $displayName = $user['full_name'] ?: $user['username'];
                $subject = 'Reset Your Overtime Portal Password';
                $htmlBody = sprintf(
                    '<p>Hello %s,</p><p>Use the link below to reset your Overtime Portal password. This link expires in %d minutes.</p><p><a href="%s">%s</a></p><p>If you did not request this, you can ignore this email.</p>',
                    h($displayName),
                    max(1, $ttlMinutes),
                    h($resetUrl),
                    h($resetUrl)
                );
                $textBody = "Hello {$displayName},\n\nUse this link to reset your Overtime Portal password. It expires in " . max(1, $ttlMinutes) . " minutes:\n{$resetUrl}\n\nIf you did not request this, you can ignore this email.";

                Mailer::send([$user['email'] => $displayName], $subject, $htmlBody, $textBody);
            }
        } catch (Throwable $e) {
            error_log('Forgot password request failed: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Forgot Password';
include __DIR__ . '/../templates/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Forgot Password</h1>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php elseif ($submitted): ?>
                    <div class="alert alert-success">If an active account matches that email address, a reset link has been sent.</div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="_token" value="<?php echo h(csrf_token()); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="email">Email Address</label>
                        <input class="form-control" type="email" name="email" id="email" required autocomplete="email" value="<?php echo h($_POST['email'] ?? ''); ?>">
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Send Reset Link</button>
                </form>
                <div class="mt-3">
                    <a href="/login.php">Back to login</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
