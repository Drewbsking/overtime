<?php
/** @var string $pageTitle */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($pageTitle ?? 'Overtime Portal'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/dashboard.php">Overtime</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto">
                <?php if (Auth::check()): ?>
                    <li class="nav-item"><a class="nav-link" href="/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/request-new.php">New Request</a></li>
                    <li class="nav-item"><a class="nav-link" href="/requests.php">My Requests</a></li>
                    <li class="nav-item"><a class="nav-link" href="/equalization.php">Equalization</a></li>
                    <?php if (Auth::isApprover()): ?>
                        <li class="nav-item"><a class="nav-link" href="/review.php">Approvals</a></li>
                    <?php endif; ?>
                    <?php if (Auth::isAdmin()): ?>
                        <li class="nav-item"><a class="nav-link" href="/admin/users.php">Users</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if (Auth::check()): ?>
                    <?php $displayName = Auth::user()['full_name'] ?? Auth::user()['username']; ?>
                    <li class="nav-item"><span class="navbar-text text-white me-3"><?php echo h($displayName); ?></span></li>
                    <li class="nav-item"><a class="nav-link" href="/logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="/login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container py-4">
    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success"><?php echo h($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger"><?php echo h($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = ($_SESSION['flash_missing_vendor'] ?? null)): ?>
        <div class="alert alert-warning"><?php echo h($msg); ?></div>
        <?php unset($_SESSION['flash_missing_vendor']); ?>
    <?php endif; ?>
