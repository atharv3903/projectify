<?php
session_start();

// Check if the user's original role is 'admin'
if ($_SESSION['original_role'] == 'admin') {
    $_SESSION['role'] = 'admin';  // Switch back to admin role
    header("Location: admin.php");
    exit();
} else {
    echo "Access denied!";
}
?>
