<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$error = null;
$board = [];
$asOf = null;
$fileUpdated = null;
$isPrivileged = Auth::isAdmin() || Auth::isApprover();

try {
    $data = EqualizationSheet::load();
    $board = $data['rows'] ?? [];
    $asOf = $data['as_of'] ?? null;
    $fileUpdated = $data['file_mtime'] ?? null;
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
<?php if ($asOf || $fileUpdated): ?>
    <p class="text-muted">
        <?php if ($asOf): ?>
            As of: <?php echo h($asOf); ?>
            <?php if ($fileUpdated): ?>&nbsp;|&nbsp;<?php endif; ?>
        <?php endif; ?>
        <?php if ($fileUpdated): ?>
            File updated: <?php echo h(date('Y-m-d H:i', $fileUpdated)); ?>
        <?php endif; ?>
    </p>
<?php endif; ?>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
        <tr>
            <th>#</th>
            <th>User</th>
            <?php if ($isPrivileged): ?>
                <th>Total Hours</th>
            <?php endif; ?>
            <th>Percentile</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $rank = 1;
        $totalCount = max(count($board), 1);
        foreach ($board as $row):
            $percentile = round(($rank / $totalCount) * 100, 1);
            ?>
            <tr>
                <td><?php echo $rank++; ?></td>
                <td><?php echo h($row['username']); ?></td>
                <?php if ($isPrivileged): ?>
                    <td><?php echo h(number_format((float)$row['total_hours'], 2)); ?></td>
                <?php endif; ?>
                <td><?php echo h($percentile . '%'); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
