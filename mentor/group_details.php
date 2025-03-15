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
    $conn->query($sql);
}
if (isset($_POST['unfreeze'])) {
    $sql = "UPDATE project SET frozen = FALSE WHERE id = '$project_id'";
    $conn->query($sql);
}

// Fetch detailed project info
$sql = "SELECT p.name AS project_name, p.description, p.status, p.frozen, p.git_repo_link, p.pdf_path 
        FROM project p WHERE p.id = '$project_id'";
$result = $conn->query($sql);
$project = $result->fetch_assoc();

// Fetch group members but exclude mentors
$members_sql = "SELECT u.name AS member_name FROM user u
                INNER JOIN user_project up ON u.id = up.user_id
                WHERE up.project_id = '$project_id' AND (u.role = 'student' OR u.role = 'group_leader')";
$members_result = $conn->query($members_sql);

// Fetch PDF path
$pdf_path = $project['pdf_path'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Details</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .navbar {
            width: 100%;
            background-color: #007bff;
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 10px;
            font-size: 16px;
        }
        .container {
            width: 80%;
            max-width: 900px;
            background: white;
            padding: 20px;
            margin: 20px auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 { color: #007bff; }
        p, a { font-size: 16px; }
        a { color: #007bff; }
        button {
            background-color: #28a745;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 0;
        }
        .freeze-btn { background-color: #dc3545; }
        .freeze-btn:hover { background-color: #c82333; }
        .my-groups ul { padding: 0; list-style: none; }
        .my-groups li { padding: 5px 0; }
    </style>
</head>
<body>
    <div class="navbar">
        Group Details | <a href="../mentor.php">Back to Dashboard</a>
    </div>
    
    <div class="container">
    <h2><?php echo htmlspecialchars($project['project_name']); ?></h2>
    <p><strong>Description:</strong> <?php echo htmlspecialchars($project['description']); ?></p>
    <p><strong>Status:</strong> <?php echo htmlspecialchars($project['status']); ?></p>
    <p>
        <strong>Git Repository:</strong> 
        <a href="<?php echo htmlspecialchars($project['git_repo_link']); ?>" target="_blank">View Repository</a>
    </p>

    <form method="POST">
        <?php if ($project['frozen']): ?>
            <button type="submit" name="unfreeze" class="freeze-btn">Unfreeze Project</button>
        <?php else: ?>
            <button type="submit" name="freeze" class="freeze-btn">Freeze Project</button>
        <?php endif; ?>
    </form>

    <hr>

    <h2>Group Members</h2>
    <ul>
        <?php while ($member = $members_result->fetch_assoc()): ?>
            <li><?php echo htmlspecialchars($member['member_name']); ?></li>
        <?php endwhile; ?>
    </ul>
</div>

    <div class="container">
        <?php include '../student/gantt_chart.php'; ?>
    </div>      
    <div class="container">
        <h2>Chat</h2>
        <?php
            $project_id = $_GET['project_id'];
        ?>
        <a href="../chat/chat.php?project_id=<?php echo urlencode($project_id); ?>">
    
        <button>Go to Chat</button>
        </a>
    </div>
    
    <div class="container">
        <h2>Project Report</h2>
        <a href="generate_report.php?project_id=<?php echo $project_id; ?>">
            <button>Download Report</button>
        </a>
        <?php if (!empty($pdf_path) && file_exists($pdf_path)): ?>
            <iframe src="<?php echo htmlspecialchars(str_replace('C:/xampp/htdocs/projectify/', '/projectify/', $pdf_path)); ?>" width="100%" height="500px"></iframe>
            <p><a href="<?php echo htmlspecialchars(str_replace('C:/xampp/htdocs/projectify/', '/projectify/', $pdf_path)); ?>" target="_blank"><button>Download PDF</button></a></p>
        <?php else: ?>
            <p>No PDF uploaded or file not found.</p>
        <?php endif; ?>
    </div>
</body>
</html>