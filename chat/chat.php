<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/projectify/db.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}

$username = $_SESSION['username'];

// Get the user's role
$user_query = "SELECT role FROM user WHERE id = '$username'";
$user_result = $conn->query($user_query);
$user = $user_result->fetch_assoc();
$role = $user['role'];

// Check if the user is linked to one or multiple projects
$project_query = "SELECT project_id FROM user_project WHERE user_id = '$username'";
$project_result = $conn->query($project_query);

if ($project_result->num_rows == 0) {
    echo "You are not associated with any project.";
    exit();
}

// Handle mentor-specific project selection
if ($role === 'mentor') {
    // If mentor hasn't selected a project yet, show project selection form
    $project_id = $_GET['project_id'];
    
} else {
    // For non-mentors (students or admins), assume they're linked to one project
    $project = $project_result->fetch_assoc();
    $project_id = $project['project_id'];
}

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = $conn->real_escape_string($_POST['message']);
    $insert_query = "INSERT INTO messages (project_id, user_id, message) 
                     VALUES ('$project_id', '$username', '$message')";
    $conn->query($insert_query);

    // Redirect to clear POST data (Prevents message resubmission on refresh)
    header("Location: " . $_SERVER['PHP_SELF'] . "?project_id=$project_id");
    exit();
}

// Fetch chat messages
$message_query = "SELECT m.message, m.timestamp, u.name, u.role
                  FROM messages m
                  JOIN user u ON m.user_id = u.id
                  WHERE m.project_id = '$project_id'
                  ORDER BY m.timestamp ASC";
$messages_result = $conn->query($message_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Group Chat</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h2>Group Chat - Project ID: <?php echo $project_id; ?></h2>

    <div class="chat-box">
        <?php while ($message = $messages_result->fetch_assoc()): ?>
            <p>
                <strong>
                    <?php 
                        echo htmlspecialchars($message['name']);
                        if ($message['role'] == 'mentor') {
                            echo " (Mentor)";
                        }
                    ?>:
                </strong>
                <?php echo htmlspecialchars($message['message']); ?>
                <small><?php echo $message['timestamp']; ?></small>
            </p>
        <?php endwhile; ?>
    </div>

    <form method="POST" action="?project_id=<?php echo $project_id; ?>">
        <textarea name="message" rows="3" cols="50" placeholder="Type your message here..." required></textarea><br>
        <button type="submit">Send</button>
    </form>

    <br>
    <?php
        // Determine the appropriate dashboard link based on the user's role
        $dashboard_link = '#';

        if ($role === 'admin') {
            $dashboard_link = '../admin.php';
        } elseif ($role === 'mentor') {
            $dashboard_link = '/projectify/mentor.php';
        } elseif ($role === 'student' || $role === 'group_leader') {
            $dashboard_link = '../student/student_frozen.php';
        }
    ?>
    <a href="<?php echo $dashboard_link; ?>"><button>Back to Dashboard</button></a>

</body>
</html>
