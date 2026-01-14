<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole(['approver', 'admin']);

if (is_post()) {
    validate_csrf();
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $decisionNote = trim($_POST['decision_note'] ?? '');
    $normalizedNote = $decisionNote === '' ? null : $decisionNote;

    $stmt = DB::conn()->prepare('SELECT r.*, u.email AS requester_email, COALESCE(u.full_name, u.username) AS requester_name, u.username AS requester_username FROM overtime_requests r JOIN users u ON u.id = r.user_id WHERE r.id = :id LIMIT 1');
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
            Overtime::approve($requestId, Auth::user()['id'], $normalizedNote);
            $newStatus = 'approved';
        } elseif ($action === 'deny') {
            if ($decisionNote === '') {
                flash('error', 'Decision note is required to deny a request.');
                redirect('/review.php');
            }
            Overtime::deny($requestId, Auth::user()['id'], $decisionNote);
            $newStatus = 'denied';
        } else {
            throw new InvalidArgumentException('Invalid action.');
        }

        $request['decision_note'] = $normalizedNote;

        $subject = sprintf('Your overtime request #%d was %s', $requestId, $newStatus);
        $emailDetails = [
            '<li>Date: ' . h($request['work_date']) . '</li>',
            '<li>Hours: ' . h($request['hours']) . '</li>',
            '<li>Work Type: ' . h(ucfirst($request['work_type'] ?? '')) . '</li>',
            '<li>Reason: ' . nl2br(h($request['reason'])) . '</li>',
        ];
        if (!empty($request['decision_note'] ?? '')) {
            $emailDetails[] = '<li>Decision Note: ' . nl2br(h($request['decision_note'])) . '</li>';
        }
        $decisionBy = Auth::user()['full_name'] ?? Auth::user()['username'];
        $emailDetails[] = '<li>Decision by: ' . h($decisionBy) . '</li>';

        $html = sprintf(
            '<p>Your overtime request was %s.</p><ul>%s</ul>',
            $newStatus,
            implode('', $emailDetails)
        );

        $recipients = Overtime::notificationRecipients();
        if (!empty($request['requester_email'])) {
            $recipients[$request['requester_email']] = $request['requester_name'];
        }

        Mailer::send($recipients, $subject, $html);

        flash('success', 'Request ' . $newStatus . '.');
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
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
                    <td>
                        <?php echo h($r['requester_name']); ?>
                        <?php if (!empty($r['requester_username']) && $r['requester_username'] !== $r['requester_name']): ?>
                            <div class="text-muted small"><?php echo h($r['requester_username']); ?></div>
                        <?php endif; ?>
                    </td>
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
                        <form method="post" class="d-flex flex-column gap-1">
                            <input type="hidden" name="_token" value="<?php echo h(csrf_token()); ?>">
                            <input type="hidden" name="request_id" value="<?php echo h($r['id']); ?>">
                            <textarea name="decision_note" class="form-control form-control-sm" rows="1" placeholder="Decision note (optional for approvals, required for denials)"></textarea>
                            <div class="d-flex gap-1">
                                <button class="btn btn-success btn-sm" type="submit" name="action" value="approve">Approve</button>
                                <button class="btn btn-danger btn-sm" type="submit" name="action" value="deny">Deny</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/../templates/footer.php'; ?>
