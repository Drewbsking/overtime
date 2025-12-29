<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$pageTitle = 'Dashboard';
include __DIR__ . '/../templates/header.php';
?>
<h1 class="h4 mb-4">Welcome, <?php echo h(Auth::user()['full_name'] ?? Auth::user()['username']); ?></h1>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Overtime Request</h5>
                <p class="card-text">Submit overtime with date, hours, and reason.</p>
                <a class="btn btn-primary" href="/request-new.php">New Request</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">My Requests</h5>
                <p class="card-text">Track your pending, approved, and denied requests.</p>
                <a class="btn btn-secondary" href="/requests.php">View Requests</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Equalization</h5>
                <p class="card-text">See the 365-day overtime rankings.</p>
                <a class="btn btn-outline-primary" href="/equalization.php">View Board</a>
            </div>
        </div>
    </div>
    <?php if (Auth::isApprover()): ?>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">Approvals</h5>
                    <p class="card-text">Review and decide on pending overtime requests.</p>
                    <a class="btn btn-warning" href="/review.php">Review Queue</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (Auth::isAdmin()): ?>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title">User Management</h5>
                    <p class="card-text">Create accounts and set approver/admin roles.</p>
                    <a class="btn btn-danger" href="/admin/users.php">Manage Users</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
