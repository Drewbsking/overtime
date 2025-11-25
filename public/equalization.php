<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$windowDays = 365;
$board = Overtime::equalizationBoard($windowDays);

$pageTitle = 'Equalization Board';
include __DIR__ . '/../templates/header.php';
?>
<h1 class="h4 mb-3">Equalization Board (last <?php echo $windowDays; ?> days)</h1>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
        <tr>
            <th>#</th>
            <th>User</th>
            <th>Total Approved Hours</th>
            <th>Last Assigned</th>
        </tr>
        </thead>
        <tbody>
        <?php $rank = 1; foreach ($board as $row): ?>
            <tr>
                <td><?php echo $rank++; ?></td>
                <td><?php echo h($row['username']); ?></td>
                <td><?php echo h(number_format((float)$row['total_hours'], 2)); ?></td>
                <td><?php echo h($row['last_assigned_at'] ?? ''); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
