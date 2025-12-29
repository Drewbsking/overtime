<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole(['approver', 'admin']);

if (is_post()) {
    validate_csrf();
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $denialReason = trim($_POST['denial_reason'] ?? '');

    $stmt = DB::conn()->prepare('SELECT r.*, u.email AS requester_email, u.username AS requester_name FROM overtime_requests r JOIN users u ON u.id = r.user_id WHERE r.id = :id LIMIT 1');
    $stmt->execute(['id' => $requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        flash('error', 'Request not found.');
        redirect('/review.php');
    }
    if ($request['status'] !== 'pending') {
        flash('error', 'Request already processed.');
        redirect('/review.php');
    }

    try {
        if ($action === 'approve') {
            Overtime::approve($requestId, Auth::user()['id']);
            $newStatus = 'approved';
        } elseif ($action === 'deny') {
            if ($denialReason === '') {
                flash('error', 'Denial reason is required.');
                redirect('/review.php');
            }
            Overtime::deny($requestId, Auth::user()['id'], $denialReason);
            $newStatus = 'denied';
            $request['denial_reason'] = $denialReason;
        } else {
            throw new InvalidArgumentException('Invalid action.');
        }

        $subject = sprintf('Your overtime request #%d was %s', $requestId, $newStatus);
        $emailDetails = [
            '<li>Date: ' . h($request['work_date']) . '</li>',
            '<li>Hours: ' . h($request['hours']) . '</li>',
            '<li>Work Type: ' . h(ucfirst($request['work_type'] ?? '')) . '</li>',
            '<li>Reason: ' . nl2br(h($request['reason'])) . '</li>',
        ];
        if ($newStatus === 'denied' && !empty($request['denial_reason'] ?? '')) {
            $emailDetails[] = '<li>Denial Reason: ' . nl2br(h($request['denial_reason'])) . '</li>';
        }
        $emailDetails[] = '<li>Decision by: ' . h(Auth::user()['username']) . '</li>';

        $html = sprintf(
            '<p>Your overtime request was %s.</p><ul>%s</ul>',
            $newStatus,
            implode('', $emailDetails)
        );

        $recipients = [
            $request['requester_email'] => $request['requester_name'],
        ];
        foreach (['SMTP_APPROVER_1', 'SMTP_APPROVER_2'] as $envKey) {
            $email = env($envKey);
            if ($email) {
                $recipients[$email] = 'Approver';
            }
        }

        Mailer::send($recipients, $subject, $html);

        flash('success', 'Request ' . $newStatus . '.');
    } catch (Throwable $e) {
        flash('error', 'Could not update request.');
    }
    redirect('/review.php');
}

$pending = Overtime::pending();
$pageTitle = 'Pending Requests';
include __DIR__ . '/../templates/header.php';
?>
<h1 class="h4 mb-3">Pending Requests</h1>
<?php if (empty($pending)): ?>
    <p>No pending requests.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>ID</th>
                <th>Requester</th>
                <th>Date</th>
                <th>Hours</th>
                <th>Work Type</th>
                <th>Reason</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pending as $r): ?>
                <tr>
                    <td><?php echo h($r['id']); ?></td>
                    <td><?php echo h($r['requester_name']); ?></td>
                    <td>
                        <?php echo h($r['work_date']); ?>
                        <?php
                        $dayOfWeek = '';
                        if (!empty($r['work_date'])) {
                            $timestamp = strtotime($r['work_date']);
                            if ($timestamp !== false) {
                                $dayOfWeek = date('l', $timestamp);
                            }
                        }
                        if ($dayOfWeek !== ''): ?>
                            <div class="text-muted small"><?php echo h($dayOfWeek); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo h($r['hours']); ?></td>
                    <td><?php echo h(ucfirst($r['work_type'] ?? '')); ?></td>
                    <td><?php echo nl2br(h($r['reason'])); ?></td>
                    <td><?php echo h($r['created_at']); ?></td>
                    <td>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="_token" value="<?php echo h(csrf_token()); ?>">
                            <input type="hidden" name="request_id" value="<?php echo h($r['id']); ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-success btn-sm" type="submit">Approve</button>
                        </form>
                        <form method="post" class="d-inline ms-1">
                            <input type="hidden" name="_token" value="<?php echo h(csrf_token()); ?>">
                            <input type="hidden" name="request_id" value="<?php echo h($r['id']); ?>">
                            <input type="hidden" name="action" value="deny">
                            <textarea name="denial_reason" class="form-control form-control-sm mb-1" rows="1" placeholder="Denial reason" required></textarea>
                            <button class="btn btn-danger btn-sm" type="submit">Deny</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/../templates/footer.php'; ?>
