<?php
session_start();
include '../db.php'; // Database connection

if (!isset($_SESSION['username']) || !isset($_POST['message'])) {
    echo "Unauthorized access.";
    exit();
}

$username = $_SESSION['username'];
$message = $conn->real_escape_string($_POST['message']);

// Identify the user's project
$project_query = "SELECT project_id FROM user_project WHERE user_id = '$username'";
$project_result = $conn->query($project_query);

if ($project_result->num_rows == 0) {
    echo "You are not part of any project.";
    exit();
}

$project = $project_result->fetch_assoc();
$project_id = $project['project_id'];

// Insert message into database
$conn->query("INSERT INTO messages (project_id, user_id, message) VALUES ('$project_id', '$username', '$message')");

// Fetch and display updated chat messages
$message_query = "SELECT m.message, m.timestamp, u.name 
                  FROM messages m
                  JOIN user u ON m.user_id = u.id
                  WHERE m.project_id = '$project_id'
                  ORDER BY m.timestamp ASC";

$message_result = $conn->query($message_query);

while ($msg = $message_result->fetch_assoc()) {
    echo "<p><strong>" . htmlspecialchars($msg['name']) . ":</strong> " . 
         htmlspecialchars($msg['message']) . 
         " <small>(" . $msg['timestamp'] . ")</small></p>";
}
?>
