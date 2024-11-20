<?php
$servername = "localhost";
$username = "root"; // MySQL username
$password = ""; // MySQL password (default is empty)
$dbname = "projectify"; // Database name
$port = 3307; // MySQL port (if changed from 3306)

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
