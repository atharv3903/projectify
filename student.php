<?php
session_start();
include 'db.php'; // Database connection

// Redirect if not logged in or not a student/group_leader
if (!isset($_SESSION['username']) || ($_SESSION['role'] != 'student' && $_SESSION['role'] != 'group_leader')) {
    header("Location: index.php");
    exit();
}

// Get user details
$user_id = $conn->real_escape_string($_SESSION['username']);
$role = $_SESSION['role'];

// Fetch user's projects (adjusted for `user_project` table)
$sql_projects = "
    SELECT p.id, p.name AS project_name, p.status, p.year_and_batch
    FROM project p
    JOIN user_project up ON p.id = up.project_id
    WHERE up.user_id = '$user_id'";
$projects_result = $conn->query($sql_projects);

// Fetch assigned tasks
$sql_tasks = "
    SELECT t.id, t.name, t.status, t.percent_complete, p.name AS project_name
    FROM task t
    JOIN project p ON t.project_id = p.id
    WHERE t.assigned_to = '$user_id'";
$tasks_result = $conn->query($sql_tasks);

// Fetch mentor information
$sql_mentors = "
    SELECT u.id, u.name, m.expertise
    FROM users u
    JOIN mentor_skills m ON u.id = m.mentor_id";
$mentors_result = $conn->query($sql_mentors);

// Handle like functionality
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['like_project'])) {
    $project_id = $conn->real_escape_string($_POST['project_id']);
    $sql_like = "
        INSERT INTO likes (id, project_id, student_id)
        VALUES (UUID(), '$project_id', '$user_id')";
    if ($conn->query($sql_like) === TRUE) {
        $like_message = "Project liked successfully!";
    } else {
        $like_message = "Error liking project: " . $conn->error;
    }
}

// Handle comment functionality
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment_project'])) {
    $project_id = $conn->real_escape_string($_POST['project_id']);
    $comment_text = $conn->real_escape_string($_POST['comment_text']);
    $sql_comment = "
        INSERT INTO comments (id, project_id, student_id, comment_text)
        VALUES (UUID(), '$project_id', '$user_id', '$comment_text')";
    if ($conn->query($sql_comment) === TRUE) {
        $comment_message = "Comment added successfully!";
    } else {
        $comment_message = "Error adding comment: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
</head>
<body>

<h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</h2>

<?php if ($role === 'group_leader'): ?>
    <p>You are a Group Leader! You have extra functionalities:</p>
    <ul>
        <li><a href="#">Manage Group</a></li>
        <li><a href="#">View Group Reports</a></li>
        <li><a href="#">Assign Tasks</a></li>
    </ul>
<?php endif; ?>

<h3>Your Projects:</h3>
<?php if ($projects_result && $projects_result->num_rows > 0): ?>
    <ul>
        <?php while ($project = $projects_result->fetch_assoc()): ?>
            <li>
                <b><?php echo htmlspecialchars($project['project_name']); ?></b> - 
                Status: <?php echo htmlspecialchars($project['status']); ?> 
                (<?php echo htmlspecialchars($project['year_and_batch']); ?>)
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>No projects assigned.</p>
<?php endif; ?>

<h3>Your Tasks:</h3>
<?php if ($tasks_result && $tasks_result->num_rows > 0): ?>
    <ul>
        <?php while ($task = $tasks_result->fetch_assoc()): ?>
            <li>
                <b><?php echo htmlspecialchars($task['name']); ?></b> in 
                Project: <?php echo htmlspecialchars($task['project_name']); ?> - 
                Status: <?php echo htmlspecialchars($task['status']); ?> - 
                <?php echo htmlspecialchars($task['percent_complete']); ?>% complete
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>No tasks assigned.</p>
<?php endif; ?>

<h3>Mentors:</h3>
<?php if ($mentors_result && $mentors_result->num_rows > 0): ?>
    <ul>
        <?php while ($mentor = $mentors_result->fetch_assoc()): ?>
            <li>
                <b><?php echo htmlspecialchars($mentor['name']); ?></b> - 
                Expertise: <?php echo htmlspecialchars($mentor['expertise']); ?>
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>No mentors available.</p>
<?php endif; ?>

<h3>Available Projects:</h3>
<?php
$sql_available_projects = "SELECT id, name AS project_name, description FROM project";
$available_projects_result = $conn->query($sql_available_projects);

if ($available_projects_result && $available_projects_result->num_rows > 0):
    while ($project = $available_projects_result->fetch_assoc()): ?>
        <h4><?php echo htmlspecialchars($project['project_name']); ?></h4>
        <p><?php echo htmlspecialchars($project['description']); ?></p>
        
        <form action="" method="POST">
            <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project['id']); ?>">
            <input type="submit" name="like_project" value="Like Project">
        </form>

        <form action="" method="POST">
            <textarea name="comment_text" required placeholder="Add your comment"></textarea><br>
            <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($project['id']); ?>">
            <input type="submit" name="comment_project" value="Comment">
        </form>

    <?php endwhile;
else:
    echo "<p>No projects available to like or comment on.</p>";
endif;
?>

<?php if (isset($like_message)) echo "<p>" . htmlspecialchars($like_message) . "</p>"; ?>
<?php if (isset($comment_message)) echo "<p>" . htmlspecialchars($comment_message) . "</p>"; ?>

<a href="logout.php">Logout</a>

</body>
</html>
