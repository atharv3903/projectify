<?php
session_start();

// Ensure that only mentors or admins can access this page
if (!isset($_SESSION['username']) || ($_SESSION['role'] !== 'admin')) {
    // Redirect to the login page if not logged in as admin
    header("Location: login.php" . "?unauthorized=1");
    exit();
}

include('../db.php');

// Fetch all projects
$projects_query = "SELECT id, name, description, keywords FROM project WHERE status = 'in_progress'";
$projects_result = $conn->query($projects_query);
$projects = [];
if ($projects_result->num_rows > 0) {
    while ($row = $projects_result->fetch_assoc()) {
        $projects[] = $row;
    }
}
$projects_result = $conn->query($projects_query);

// Fetch all mentors
$mentors_query = "SELECT id, name, expertise FROM mentor_skills 
                    JOIN user ON mentor_skills.mentor_id = user.id 
                    WHERE role IN ('mentor', 'admin')
                    GROUP BY id";
$mentors_result = $conn->query($mentors_query);
$mentors = [];
if ($mentors_result->num_rows > 0) {
    while ($row = $mentors_result->fetch_assoc()) {
        $mentors[] = $row;
    }
}

// Prepare data for Python (JSON)
$projects_json = json_encode($projects);
$mentors_json = json_encode($mentors);

// Call Python script
$command = "py get_mentor_suggestions.py " . escapeshellarg($projects_json) . " " . escapeshellarg($mentors_json) . " 2>&1";
$output = shell_exec($command);

$warning_position = strpos($output, 'WARNING:');

if ($warning_position !== false) { // remove excess output
    $output = substr($output, 0, $warning_position);
    $output = htmlspecialchars($output);
}


// Process Python output (assuming one mentor name per line)
$suggested_mentors = explode("\n", trim($output));

// Process form submission (update mentor mapping)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_mapping'])) {
    foreach ($_POST['mentor_mapping'] as $project_id => $mentor_id) {
        // Delete the previous mentor assignment for the project
        $delete_query = "DELETE FROM user_project WHERE project_id = ? AND user_id IN (SELECT id from user where role IN ('mentor','admin'))";
        $stmt_delete = $conn->prepare($delete_query);
        $stmt_delete->bind_param("s", $project_id);
        $stmt_delete->execute();

        // Insert the new mentor assignment
        $update_query = "INSERT INTO user_project (project_id, user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ss", $project_id, $mentor_id);
        $stmt->execute();
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Mentor</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="navbar">    
    <h2>Projectify</h2>
    <a href="../index.php">Home</a>
    <a href="../all_projects.php">View All Projects</a>
    <a href="switch_to_mentor.php">Mentor mode</a>
    <a href="match_mentors.php">Match Mentors</a>
    <a href="manageUsers.php">Manage Users</a>
    <!-- <a href="oldPDFUpload.php">Upload (Previous) pdfs</a> -->
    <a href="../logout.php">Logout</a>
</div>




    <div class="section-container">
        <h2>Assign Mentors</h2>

        <?php
        if (isset($_GET['success']) && $_GET['success'] == 1) {
            echo "<p class='success-message'>Mentors Assigned successfully!</p>";
        }
        ?>

        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
            <table border="1">
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Project Desc</th>
                        <th>Group Members</th>
                        <th>Assign Mentor</th>
                        <th>Suggested Mentor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $index => $project): ?>
                        <tr>
                            <td><?= htmlspecialchars($project['name']) ?></td>
                            <td><?= htmlspecialchars($project['description']) ?></td>
                            <td>
                                <?php
                                $project_id = $project['id'];
                                $members_query = "SELECT name FROM user 
                                                WHERE id IN (
                                                    SELECT user_id FROM user_project
                                                    WHERE project_id = ?)
                                                AND role IN ('student','group_leader')";
                                $stmt = $conn->prepare($members_query);
                                $stmt->bind_param("s", $project_id);
                                $stmt->execute();
                                $members_result = $stmt->get_result();
                                $members = [];
                                while ($member = $members_result->fetch_assoc()) {
                                    $members[] = htmlspecialchars($member['name']);
                                }
                                echo implode(", ", $members);

                                // Fetch the current mentor for the project
                                $current_mentor_query = "
                                    SELECT id
                                    FROM user
                                    WHERE id IN (
                                        SELECT user_id
                                        FROM user_project
                                        WHERE project_id = ?
                                    )
                                    AND role IN ('mentor', 'admin')
                                ";
                                $stmt_mentor = $conn->prepare($current_mentor_query);
                                $stmt_mentor->bind_param("s", $project_id);
                                $stmt_mentor->execute();
                                $current_mentor_result = $stmt_mentor->get_result();
                                $current_mentor_id = null;
                                if ($current_mentor_result->num_rows > 0) {
                                    $row = $current_mentor_result->fetch_assoc();
                                    $current_mentor_id = $row['id'];
                                }
                                ?>
                            </td>
                            <td>
                                <select name="mentor_mapping[<?= htmlspecialchars($project_id) ?>]">
                                    <?php foreach ($mentors as $mentor): ?>
                                        <option value="<?= htmlspecialchars($mentor['id']) ?>" 
                                            <?php if ($current_mentor_id == $mentor['id']) echo 'selected'; ?>>
                                            <?= htmlspecialchars($mentor['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <?php
                                    if (isset($suggested_mentors[$index])) {
                                        echo htmlspecialchars($suggested_mentors[$index]);
                                    } else {
                                        echo "No suggestion";
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="button-container">
                <input type="submit" name="save_mapping" value="Save" class="primary-button">
            </div>
        </form>
    </div>
</body>
</html>
