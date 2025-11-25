// smtp_test.php
<?php
$host = 'overtime.rcocwiki.org'; // Replace with your SMTP server
$port = 465; // Replace with your SMTP port

$connection = fsockopen($host, $port, $errno, $errstr, 30);
if (!$connection) {
    echo "Connection to SMTP server failed: $errstr ($errno)<br />\n";
} else {
    echo "Connected to SMTP server successfully.<br />\n";
    fclose($connection);
}
?>
