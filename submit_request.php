<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';  // Ensure this file exists and has correct database details

if (!isset($_SESSION['user_id'])) {
    die("Access denied. Please log in.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $name = htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8');
    $hours = filter_var($_POST['hours'], FILTER_SANITIZE_NUMBER_INT);
    $reason = htmlspecialchars($_POST['reason'], ENT_QUOTES, 'UTF-8');

    // Check for empty fields
    if (empty($name) || empty($hours) || empty($reason)) {
        die("All fields are required.");
    }

    // Insert request into database
    $stmt = $conn->prepare("INSERT INTO requests (user_id, name, hours, reason) VALUES (?, ?, ?, ?)");
    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }
    $stmt->bind_param("isis", $user_id, $name, $hours, $reason);

    if ($stmt->execute()) {
        echo "Request submitted successfully.<br>";

        // Fetch approvers' emails and send approval email
        $approver_query = "SELECT email FROM users WHERE is_approver = 1";
        $approvers = $conn->query($approver_query);

        while ($approver = $approvers->fetch_assoc()) {
            sendApprovalEmail($approver['email'], ['name' => $name, 'hours' => $hours, 'reason' => $reason]);
        }
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
    <title>Submit Request</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h2 class="my-4">Submit Overtime Request</h2>
        <form action="submit_request.php" method="post">
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="hours">Overtime Hours:</label>
                <input type="number" class="form-control" id="hours" name="hours" required>
            </div>
            <div class="form-group">
                <label for="reason">Reason:</label>
                <textarea class="form-control" id="reason" name="reason" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Submit Request</button>
        </form>
    </div>
</body>
</html>
