<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireRole(['admin']);

function generateTempPassword(): string
{
    return bin2hex(random_bytes(6)); // 12 chars
}

if (is_post()) {
    validate_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'user';
        $tempPassword = $_POST['temp_password'] ?: generateTempPassword();

        $allowedRoles = ['user', 'approver', 'admin'];
        if (!in_array($role, $allowedRoles, true)) {
            flash('error', 'Invalid role.');
            redirect('/admin/users.php');
        }

        if (!$username || !$email || !$fullName) {
            flash('error', 'Username, full name, and email are required.');
            redirect('/admin/users.php');
        }

        try {
            $userId = Auth::createUser($username, $fullName, $email, $tempPassword, $role);
            // Email the new user with temp credentials (best-effort; failure is logged)
            $loginUrl = app_url('login.php');
            $subject = 'Your Overtime Portal Account';
            $htmlBody = sprintf(
                '<p>Hello %s,</p><p>An account was created for you.</p><ul><li>Username: %s</li><li>Temporary password: %s</li></ul><p>Please sign in at <a href="%s">%s</a> and change your password immediately.</p>',
                h($fullName ?: $username),
                h($username),
                h($tempPassword),
                h($loginUrl),
                h($loginUrl)
            );
            Mailer::send([$email => ($fullName ?: $username)], $subject, $htmlBody);

            flash('success', "User created. Temp password: {$tempPassword}");
        } catch (Throwable $e) {
            flash('error', 'Could not create user (username/email may already exist).');
        }
        redirect('/admin/users.php');
    }

    if ($action === 'reset') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $tempPassword = generateTempPassword();
        try {
            Auth::setPassword($userId, $tempPassword, true);
            flash('success', "Password reset. New temp password: {$tempPassword}");
        } catch (Throwable $e) {
            flash('error', 'Could not reset password.');
        }
        redirect('/admin/users.php');
    }

    if ($action === 'toggle') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $active = (int)($_POST['is_active'] ?? 0);
        $stmt = DB::conn()->prepare('UPDATE users SET is_active = :active, updated_at = :updatedAt WHERE id = :id');
        $stmt->execute(['active' => $active, 'updatedAt' => now(), 'id' => $userId]);
        flash('success', 'User updated.');
        redirect('/admin/users.php');
    }

    if ($action === 'notify') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $notify = isset($_POST['notify_on_request']) ? 1 : 0;

        $stmt = DB::conn()->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            flash('error', 'User not found.');
            redirect('/admin/users.php');
        }

        if (!in_array($user['role'], ['admin', 'approver'], true)) {
            flash('error', 'Only admins and approvers can receive request emails.');
            redirect('/admin/users.php');
        }

        $update = DB::conn()->prepare('UPDATE users SET notify_on_request = :notify, updated_at = :updatedAt WHERE id = :id');
        $update->execute([
            'notify' => $notify,
            'updatedAt' => now(),
            'id' => $userId,
        ]);

        flash('success', 'Notification preference updated.');
        redirect('/admin/users.php');
    }
}

$users = DB::conn()->query('SELECT id, username, full_name, email, role, notify_on_request, is_active, must_reset, last_login_at, created_at FROM users ORDER BY username')->fetchAll();

$pageTitle = 'Manage Users';
include __DIR__ . '/../../templates/header.php';
?>
<h1 class="h4 mb-3">Manage Users</h1>
<div class="row">
    <div class="col-md-5">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h5 mb-3">Create User</h2>
                <form method="post">
                    <input type="hidden" name="_token" value="<?php echo h(csrf_token()); ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control" type="text" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="full_name">Full Name</label>
                        <input class="form-control" type="text" id="full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" type="email" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select" id="role" name="role">
                            <option value="user">User</option>
                            <option value="approver">Approver</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="temp_password">Temp Password (optional)</label>
                        <input class="form-control" type="text" id="temp_password" name="temp_password" placeholder="Leave blank to auto-generate">
                    </div>
                    <button class="btn btn-primary" type="submit">Create</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Users</h2>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Active</th>
                            <th>Emails</th>
                            <th>Must Reset</th>
                            <th>Last Login</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo h($u['full_name'] ?? ''); ?></td>
                                <td><?php echo h($u['username']); ?></td>
                                <td><?php echo h($u['email']); ?></td>
                                <td class="text-capitalize"><?php echo h($u['role']); ?></td>
                                <td><?php echo $u['is_active'] ? 'Yes' : 'No'; ?></td>
                                <td>
                                    <?php if (in_array($u['role'], ['admin', 'approver'], true)): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="_token" value="<?php echo h(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="notify">
                                            <input type="hidden" name="user_id" value="<?php echo h($u['id']); ?>">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" name="notify_on_request" value="1" <?php echo $u['notify_on_request'] ? 'checked' : ''; ?> onchange="this.form.submit();">
                                                <label class="form-check-label">OT emails</label>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $u['must_reset'] ? 'Yes' : 'No'; ?></td>
                                <td><?php echo h($u['last_login_at'] ?? ''); ?></td>
                                <td class="text-nowrap">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="_token" value="<?php echo h(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="reset">
                                        <input type="hidden" name="user_id" value="<?php echo h($u['id']); ?>">
                                        <button class="btn btn-outline-secondary btn-sm" type="submit">Reset PW</button>
                                    </form>
                                    <form method="post" class="d-inline ms-1">
                                        <input type="hidden" name="_token" value="<?php echo h(csrf_token()); ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="user_id" value="<?php echo h($u['id']); ?>">
                                        <input type="hidden" name="is_active" value="<?php echo $u['is_active'] ? 0 : 1; ?>">
                                        <button class="btn btn-outline-<?php echo $u['is_active'] ? 'danger' : 'success'; ?> btn-sm" type="submit">
                                            <?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../templates/footer.php'; ?>
