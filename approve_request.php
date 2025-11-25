<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';  // Ensure this file exists and has correct database details
include 'email_functions.php'; // Include the email functions

// Check if the user is logged in and is an approver
if (!isset($_SESSION['user_id']) || !$_SESSION['is_approver']) {
    die("Access denied. Only approvers can approve requests.");
}

$pending_requests = [];
try {
    // Fetch all pending requests
    $stmt = $conn->prepare("SELECT requests.id, requests.name, requests.hours, requests.reason, users.username as requestor FROM requests JOIN users ON requests.user_id = users.id WHERE requests.status = 'pending'");
    if ($stmt === false) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pending_requests[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $request_id = filter_var($_POST['request_id'], FILTER_SANITIZE_NUMBER_INT);
    $action = htmlspecialchars($_POST['action'], ENT_QUOTES, 'UTF-8'); // 'approve' or 'decline'
    $approver_id = $_SESSION['user_id'];
    $approval_date = date('Y-m-d H:i:s');

    // Check for valid action
    if ($action !== 'approve' && $action !== 'decline') {
        die("Invalid action.");
    }

    // Update the request status in the database
    $status = $action === 'approve' ? 'approved' : 'declined';
    $stmt = $conn->prepare("UPDATE requests SET status = ?, approver_id = ?, approval_date = ? WHERE id = ?");
    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }
    $stmt->bind_param("sisi", $status, $approver_id, $approval_date, $request_id);

    if ($stmt->execute()) {
        echo "Request $status successfully.<br>";

        // Fetch the requestor's email
        $request_query = "SELECT users.email, requests.name, requests.hours, requests.reason FROM requests JOIN users ON requests.user_id = users.id WHERE requests.id = ?";
        $stmt = $conn->prepare($request_query);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();

        // Send email to the requestor
        if ($status === 'approved') {
            sendApprovalEmailToRequestor($request['email'], ['name' => $request['name'], 'hours' => $request['hours'], 'reason' => $request['reason']]);
        } else {
            sendDeclineEmailToRequestor($request['email'], ['name' => $request['name'], 'hours' => $request['hours'], 'reason' => $request['reason']]);
        }

        // Log the action in the audit log
        $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, action) VALUES (?, ?)");
        $log_stmt->bind_param("is", $approver_id, $action);
        $log_stmt->execute();
        $log_stmt->close();
    } else {
        die("Error: " . $stmt->error);
    }

    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Approve/Decline Requests</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h2 class="my-4">Approve/Decline Overtime Requests</h2>
        <?php if (count($pending_requests) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Hours</th>
                        <th>Reason</th>
                        <th>Requestor</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_requests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($request['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($request['hours'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($request['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($request['requestor'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <form action="approve_request.php" method="post" style="display:inline;">
                                    <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success">Approve</button>
                                </form>
                                <form action="approve_request.php" method="post" style="display:inline;">
                                    <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="decline">
                                    <button type="submit" class="btn btn-danger">Decline</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No pending requests found.</p>
        <?php endif; ?>
    </div>
</body>
</html>
