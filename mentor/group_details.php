<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/projectify/db.php';

// Check if the user is logged in and is a mentor
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'mentor') {
    header("Location: ../index.php"); // Redirect to login if not a mentor
    exit();
}

// Get the project ID from the URL
$project_id = isset($_GET['project_id']) ? $_GET['project_id'] : null;

if (!$project_id) {
    echo "Project not found!";
    exit();
}

// Handle freezing/unfreezing the project
if (isset($_POST['freeze'])) {
    $sql = "UPDATE project SET frozen = TRUE WHERE id = '$project_id'";
    if ($conn->query($sql) === TRUE) {
        echo "Project frozen successfully!";
    } else {
        echo "Error freezing project: " . $conn->error;
    }
}

if (isset($_POST['unfreeze'])) {
    $sql = "UPDATE project SET frozen = FALSE WHERE id = '$project_id'";
    if ($conn->query($sql) === TRUE) {
        echo "Project unfrozen successfully!";
    } else {
        echo "Error unfreezing project: " . $conn->error;
    }
}

// Fetch detailed project info
$sql = "SELECT p.name AS project_name, p.description, p.status, p.frozen, p.git_repo_link 
        FROM project p
        WHERE p.id = '$project_id'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $project = $result->fetch_assoc();
} else {
    echo "Project details not found!";
    exit();
}

// Fetch group members but exclude mentors
$members_sql = "SELECT u.name AS member_name
                FROM user u
                INNER JOIN user_project up ON u.id = up.user_id
                WHERE up.project_id = '$project_id' AND (u.role = 'student' OR u.role = 'group_leader')"; // Exclude mentors
$members_result = $conn->query($members_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Details</title>
    <!-- <link rel="stylesheet" href="mentorStyles.css"> -->
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .navbar {
            width: 100%;
            background-color: #2563eb;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            padding: 15px 30px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .navbar a {
            color: #ffffff;
            text-decoration: none;
        }
        .profile, .my-groups {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            width: 90%;
            max-width: 800px;
            margin: 20px 0;
        }
        h2 {
            color: #3b82f6;
        }
        .freeze-btn {
            background-color: #ef4444;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .freeze-btn:hover {
            background-color: #dc2626;
        }
        button {
            background-color: #10b981;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        button:hover {
            background-color: #059669;
        }
        a button {
            width: 100%;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            background-color: #e0f2fe;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div>Group Details</div>
        <div>
            <a href="../mentor.php">Back to Dashboard</a>
        </div>
    </div>

    <div class="profile">
        <h2><?php echo htmlspecialchars($project['project_name']); ?></h2>
        <p><strong>Description:</strong> <?php echo htmlspecialchars($project['description']); ?></p>
        <p><strong>Status:</strong> <?php echo htmlspecialchars($project['status']); ?></p>
        <p><strong>Git Repository Link:</strong> 
            <a href="<?php echo htmlspecialchars($project['git_repo_link']); ?>" target="_blank">
                <?php echo htmlspecialchars($project['git_repo_link']); ?>
            </a>
        </p>

        <form method="POST">
            <?php if ($project['frozen']): ?>
                <button type="submit" name="unfreeze" class="freeze-btn">Unfreeze Project</button>
            <?php else: ?>
                <button type="submit" name="freeze" class="freeze-btn">Freeze Project</button>
            <?php endif; ?>
        </form>
    </div>

    <div class="my-groups">
        <h2>Group Members</h2>
        <?php if ($members_result->num_rows > 0): ?>
            <ul>
                <?php while ($member = $members_result->fetch_assoc()): ?>
                    <li><?php echo htmlspecialchars($member['member_name']); ?></li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p>No members in this group.</p>
        <?php endif; ?>
    </div>

    <div class="my-groups">

    <?php include '../student/gantt_chart.php'; ?>

    </div>

    <div class="my-groups">
        <h2>Chat</h2>
        <?php
        $project_id = isset($_GET['project_id']) ? $_GET['project_id'] : null;
        ?>
        <a href="../chat/chat.php?project_id=<?php echo urlencode($project_id); ?>">
            <button>Go to Chat</button>
        </a>
    </div>
</body>
</html>
