<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$error = null;
$board = [];
$asOf = null;
$fileUpdated = null;
$stats = null;

try {
    $data = EqualizationSheet::load();
    $board = $data['rows'] ?? [];
    $asOf = $data['as_of'] ?? null;
    $fileUpdated = $data['file_mtime'] ?? null;

    if (!empty($board)) {
        $hours = array_map(static fn($row) => (float)($row['total_hours'] ?? 0), $board);
        sort($hours, SORT_NUMERIC);
        $count = count($hours);
        $mid = intdiv($count, 2);
        $median = ($count % 2 === 0) ? (($hours[$mid - 1] + $hours[$mid]) / 2) : $hours[$mid];

        $stats = [
            'min' => $hours[0],
            'median' => $median,
            'max' => $hours[$count - 1],
        ];
    }
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
<?php if ($stats): ?>
    <div class="mb-3">
        <h2 class="h6 mb-2">Overtime Stats</h2>
        <div class="d-flex gap-3 flex-wrap">
            <div>Min: <?php echo h(number_format($stats['min'], 2)); ?></div>
            <div>Median: <?php echo h(number_format($stats['median'], 2)); ?></div>
            <div>Max: <?php echo h(number_format($stats['max'], 2)); ?></div>
        </div>
    </div>
<?php endif; ?>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
        <tr>
            <th>#</th>
            <th>User</th>
            <th>Total Hours</th>
            <th>Percentile</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $rank = 1;
        $totalCount = max(count($board), 1);
        $percentileByHours = [];
        foreach ($board as $row):
            $hoursVal = (float)($row['total_hours'] ?? 0);
            $hoursKey = number_format($hoursVal, 4, '.', '');
            if (!array_key_exists($hoursKey, $percentileByHours)) {
                // Assign the same percentile to ties based on the first occurrence
                $percentileByHours[$hoursKey] = $totalCount > 1
                    ? ((($rank - 1) / ($totalCount - 1)) * 100)
                    : 100.0;
            }
            // Percentile spreads from 0% (lowest) to 100% (highest)
            $percentile = $percentileByHours[$hoursKey];
            ?>
            <tr>
                <td><?php echo $rank++; ?></td>
                <td><?php echo h($row['username']); ?></td>
                <td><?php echo h(number_format($hoursVal, 2)); ?></td>
                <td><?php echo h(number_format($percentile, 2) . '%'); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
