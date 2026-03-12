<?php
$servername = "sql105.infinityfree.com";
$username = "if0_39854433";
$password = "Poojitha73";
$dbname = "if0_39854433_placementsystem";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

