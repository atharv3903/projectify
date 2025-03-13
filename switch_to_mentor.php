<?php
session_start();

// Check if the user has both 'admin' and 'mentor' access
if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'mentor') {
    $_SESSION['role'] = 'mentor';  // Temporarily switch role to mentor
    header("Location: mentor.php");
    exit();
} else {
    echo "Access denied!";
}
?>
