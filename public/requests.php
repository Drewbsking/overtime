<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$requests = Overtime::forUser(Auth::user()['id']);

$pageTitle = 'My Requests';
include __DIR__ . '/../templates/header.php';
?>
<h1 class="h4 mb-3">My Overtime Requests</h1>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
        <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Hours</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Approver</th>
            <th>Decided At</th>
            <th>Submitted</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($requests as $r): ?>
            <tr>
                <td><?php echo h($r['id']); ?></td>
                <td><?php echo h($r['work_date']); ?></td>
                <td><?php echo h($r['hours']); ?></td>
                <td><?php echo nl2br(h($r['reason'])); ?></td>
                <td class="text-capitalize"><?php echo h($r['status']); ?></td>
                <td><?php echo h($r['approver_name'] ?? ''); ?></td>
                <td><?php echo h($r['decided_at'] ?? ''); ?></td>
                <td><?php echo h($r['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
