<?php
$host = 'localhost';
$username = 'root'; // or your actual MySQL username
$password = ''; // or your actual password, often blank for XAMPP
$database = 'warehouse'; // your actual database name

$db = new mysqli($host, $username, $password, $database);

// Check for connection error
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}
?>
