<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/projectify/db.php';

// Ensure the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

$username = $_SESSION['username'];

// Get the user's role
$user_query = "SELECT role FROM user WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("s", $username);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$role = $user['role'];

// Determine project_id
if ($role === 'mentor' && isset($_GET['project_id'])) {
    $project_id = $_GET['project_id'];
} else {
    // For non-mentors (students/admins), assume one linked project
    $project_query = "SELECT project_id FROM user_project WHERE user_id = ?";
    $stmt = $conn->prepare($project_query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $project_result = $stmt->get_result();

    if ($project_result->num_rows == 0) {
        echo "You are not associated with any project.";
        exit();
    }

    $project = $project_result->fetch_assoc();
    $project_id = $project['project_id'];
}

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = $conn->real_escape_string($_POST['message']);
    $insert_query = "INSERT INTO messages (project_id, user_id, message) 
                     VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("sss", $project_id, $username, $message);
    $stmt->execute();

    // Redirect to clear POST data (Prevents resubmission issues)
    header("Location: chat.php?project_id=" . urlencode($project_id));
    exit();
}

// Fetch chat messages
$message_query = "SELECT m.message, m.timestamp, u.name, u.role
                  FROM messages m
                  JOIN user u ON m.user_id = u.id
                  WHERE m.project_id = ?
                  ORDER BY m.timestamp ASC";
$stmt = $conn->prepare($message_query);
$stmt->bind_param("s", $project_id);
$stmt->execute();
$messages_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Group Chat</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h2>Group Chat - Project ID: <?php echo htmlspecialchars($project_id); ?></h2>

    <div class="chat-box">
        <?php while ($message = $messages_result->fetch_assoc()): ?>
            <p>
                <strong>
                    <?php 
                        echo htmlspecialchars($message['name']);
                        if ($message['role'] === 'mentor') {
                            echo " (Mentor)";
                        }
                    ?>:
                </strong>
                <?php echo htmlspecialchars($message['message']); ?>
                <small><?php echo $message['timestamp']; ?></small>
            </p>
        <?php endwhile; ?>
    </div>

    <form method="POST" action="chat.php?project_id=<?php echo urlencode($project_id); ?>">
        <textarea name="message" rows="3" cols="50" placeholder="Type your message here..." required></textarea><br>
        <button type="submit">Send</button>
    </form>

    <br>
    <?php
        // Determine the appropriate dashboard link
        $dashboard_link = ($role === 'admin') ? '../admin.php' :
                          (($role === 'mentor') ? '/projectify/mentor.php' :
                          '../student/student_frozen.php');
    ?>
    <a href="<?php echo $dashboard_link; ?>"><button>Back to Dashboard</button></a>
</body>
</html>
