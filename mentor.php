<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'mentor') {
    header("Location: index.php");
    exit();
}

echo "<h2>Welcome, " . $_SESSION['username'] . " (Mentor)</h2>";

// Add a logout link
echo '<a href="logout.php">Logout</a>';
?>
