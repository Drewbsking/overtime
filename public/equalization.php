<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$error = null;
$board = [];

try {
    $board = EqualizationSheet::load();
} catch (Throwable $e) {
    $error = 'Equalization file could not be loaded: ' . $e->getMessage();
}

$pageTitle = 'Equalization Board';
include __DIR__ . '/../templates/header.php';
?>
<h1 class="h4 mb-3">Equalization Board (CSV)</h1>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
        <tr>
            <th>#</th>
            <th>User</th>
            <th>Total Hours</th>
        </tr>
        </thead>
        <tbody>
        <?php $rank = 1; foreach ($board as $row): ?>
            <tr>
                <td><?php echo $rank++; ?></td>
                <td><?php echo h($row['username']); ?></td>
                <td><?php echo h(number_format((float)$row['total_hours'], 2)); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
