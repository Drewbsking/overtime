// dns_test.php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'overtime.rcocwiki.org'; // Replace with your SMTP server

if (checkdnsrr($host, 'A') || checkdnsrr($host, 'MX')) {
    echo "DNS resolution for $host is working.";
} else {
    echo "DNS resolution for $host failed.";
}
?>
