<?php
$servername = "localhost";
$username = "rcocwiki_admin";
$password = "Facts!Food";
$dbname = "rcocwiki_overtime";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully";
?>
