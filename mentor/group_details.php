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
    <link rel="stylesheet" href="mentorStyles.css"> <!-- Link to your CSS -->
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .navbar {
            background-color: #343a40;
            color: white;
            padding: 10px;
            text-align: center;
        }
        .navbar a {
            color: #ffffff;
            text-decoration: none;
            padding: 8px;
            border-radius: 4px;
            background-color: #007bff;
        }
        .navbar a:hover {
            background-color: #0056b3;
        }
        .profile, .my-groups {
            background-color: white;
            padding: 20px;
            margin: 20px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .profile h2 {
            color: #007bff;
        }
        button {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        button:hover {
            background-color: #218838;
        }
        .freeze-btn {
            background-color: #dc3545;
        }
        .freeze-btn:hover {
            background-color: #c82333;
        }
        .my-groups ul {
            list-style-type: none;
            padding: 0;
        }
        .my-groups li {
            padding: 5px 0;
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

    <!-- Freeze/Unfreeze Buttons -->
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

<!-- Include the Gantt chart -->
<?php include '../student/gantt_chart.php'; ?>

<div class="my-groups">
<h2>Chat</h2>
        
        <?php
        // Get the project ID from the URL
$project_id = isset($_GET['project_id']) ? $_GET['project_id'] : null;

?>
<!-- <?php echo ($project_id); ?> -->

        <a href="../chat/chat.php?project_id=<?php echo urlencode($project_id); ?>">
            <button>Go to Chat</button>
        </a>
</div>






</body>
</html>

