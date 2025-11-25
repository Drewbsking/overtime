<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole(['approver', 'admin']);

if (is_post()) {
    validate_csrf();
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';

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
            Overtime::deny($requestId, Auth::user()['id']);
            $newStatus = 'denied';
        } else {
            throw new InvalidArgumentException('Invalid action.');
        }

        $subject = sprintf('Your overtime request #%d was %s', $requestId, $newStatus);
        $html = sprintf(
            '<p>Your overtime request was %s.</p><ul><li>Date: %s</li><li>Hours: %s</li><li>Reason: %s</li><li>Decision by: %s</li></ul>',
            $newStatus,
            h($request['work_date']),
            h($request['hours']),
            nl2br(h($request['reason'])),
            h(Auth::user()['username'])
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
                    <td><?php echo h($r['work_date']); ?></td>
                    <td><?php echo h($r['hours']); ?></td>
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
