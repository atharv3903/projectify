<?php
    $project_id = $_SESSION['project_id'];
?>
<head>
    <link rel="stylesheet" href="styles.css">
</head>
<div class="navbar">
    <h2>Projectify</h2>
    <a href="index.php">Home</a>
    <a href="../chat/chat.php?project_id=<?php echo urlencode($project_id); ?>">Go to CHAT</a>
    <a href="../all_projects.php">View All Projects</a>
    <a href="../logout.php">Logout</a>
</div>
