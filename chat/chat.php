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
if ( ( $role === 'mentor' || $role === 'admin' ) && isset($_GET['project_id'])) {
    //echo "Inside IF block: role is either 'mentor' or 'admin' and project_id is set.<br>";
    $project_id = $_GET['project_id'];
    //echo $project_id;
} else {
    echo "Inside ELSE block: Either role is not 'mentor' or 'admin', or project_id is not set.<br>";
    
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
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f3f4f6;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .chat-container {
            width: 100%;
            max-width: 600px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            display: flex;
            flex-direction: column;
            height: 90vh;
        }
        .chat-box {
            flex-grow: 1;
            overflow-y: auto;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            background-color: #f9fafb;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }
        .chat-message {
            background: #e0f2fe;
            border-radius: 18px;
            padding: 10px 15px;
            margin-bottom: 10px;
            width: fit-content;
            max-width: 90%;
        }
        .chat-message strong {
            color: #0284c7;
        }
        .mentor-badge {
            background: #f59e0b;
            color: #fff;
            padding: 2px 6px;
            border-radius: 6px;
            margin-left: 5px;
            font-size: 12px;
        }
        textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            resize: none;
        }
        button {
            background-color: #3b82f6;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background-color: #2563eb;
        }
        .dashboard-btn {
            background-color: #10b981;
            margin-top: 15px;
        }
    </style>
</head>

<body>
    <div class="chat-container">
        <h2>Group Chat - Project ID: <?php echo htmlspecialchars($project_id); ?></h2>

        <div class="chat-box" id="chat-box">
            <?php while ($message = $messages_result->fetch_assoc()): ?>
                <div class="chat-message">
                    <strong>
                        <?php echo htmlspecialchars($message['name']); ?>
                        <?php if  ( ($message['role'] === 'mentor' ) || ($message['role'] === 'admin' )): ?>
                            <span class="mentor-badge">Mentor</span>
                        <?php endif; ?>
                    </strong>
                    <p>
                        <?php echo str_replace(["\\r\\n", "\\n", "\\r"], "<br>", htmlspecialchars($message['message'])); ?>
                    </p>
                    <small><?php echo $message['timestamp']; ?></small>
                </div>
            <?php endwhile; ?>
        </div>

        <form method="POST" action="chat.php?project_id=<?php echo urlencode($project_id); ?>">
            <textarea name="message" rows="3" placeholder="Type your message here..." required></textarea><br>
            <button type="submit">Send</button>
        </form>

        <?php
        // Determine the appropriate dashboard link
        $dashboard_link = ($role === 'admin') ? '../admin.php' :
                          (($role === 'mentor') ? '/projectify/mentor.php' :
                          '../student/student_frozen.php');
    ?>

        <a href="<?php echo $dashboard_link; ?>">
            <button class="dashboard-btn">Back to Dashboard</button>
        </a>
    </div>

<script>
    // Auto-scroll to bottom
    const chatBox = document.getElementById('chat-box');
    chatBox.scrollTop = chatBox.scrollHeight;
</script>
</body>


</html>
