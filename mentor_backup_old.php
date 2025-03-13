<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'mentor') {
    header("Location: index.php");
    exit();
}

// Display welcome message
echo "<h2>Welcome, " . $_SESSION['username'] . " (Mentor)</h2>";

// Add "Switch to Admin Mode" button for users who are admins
if ($_SESSION['original_role'] == 'admin') {
    echo '
    <form method="POST" action="switch_to_admin.php" style="display:inline;">
        <button type="submit">Switch to Admin Mode</button>
    </form>';
}

?>
<div class="section-container">
    <h3>Group Communication</h3>
    <button onclick="window.location.href = 'chat/chat.php';">Open Group Chat</button>
</div>

<?php

// Logout button
echo '<br><a href="logout.php">Logout</a>';
?>
