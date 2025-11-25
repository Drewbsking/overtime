<?php
$servername = "localhost";  // Usually "localhost" for most hosting providers
$username = "rcocwiki_admin";  // Your MySQL username
$password = "Facts!Food";  // Your MySQL password
$dbname = "rcocwiki_overtime";  // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
