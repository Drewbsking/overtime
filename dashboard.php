<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h2 class="my-4">Dashboard</h2>
        <p>Welcome, <?php echo $_SESSION['username']; ?>!</p>
        <a href="submit_request.php" class="btn btn-primary">Submit Overtime Request</a>
        <a href="view_requests.php" class="btn btn-secondary">View Your Requests</a>
        <?php if ($_SESSION['is_approver']): ?>
            <a href="approve_request.php" class="btn btn-warning">Approve/Decline Requests</a>
        <?php endif; ?>
        <?php if ($_SESSION['role'] == 'admin'): ?>
            <a href="admin_register.php" class="btn btn-danger">Register Users</a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>
</body>
</html>
