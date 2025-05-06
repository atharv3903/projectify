<?php
session_start();
include '../db.php'; // Include the database connection file

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php"); // Redirect to login page if not logged in
    exit();
}

// Fetch user information from the database
$username = $_SESSION['username'];
$query = "SELECT * FROM user WHERE id = '$username'";
$result = $conn->query($query);

// Check if the user exists
if ($result->num_rows == 0) {
    die("User not found.");
}

$user = $result->fetch_assoc();

// Ensure the user is not in a group
if ($user['in_group']) {
    // Redirect to student_in_group.php if in a group
    header("Location: student_in_group.php");
    exit();
}

// Check if the form is submitted to create a new group
if (isset($_POST['create_group'])) {
    // Get the form data
    $project_name = $_POST['project_name'];
    $project_description = $_POST['project_description'];
    $year_and_batch = $_POST['year_and_batch'];
    $keywords = $_POST['keywords'];
    $git_repo_link = $_POST['git_repo_link'];
    $interested_domains = $_POST['interested_domains'];

    // Create a new project ID (UUID or other unique identifier)
    $project_id = uniqid('proj_', true);

    // Insert the new project into the project table
    $sql = "INSERT INTO project (id, name, description, keywords, year_and_batch, status, git_repo_link, frozen, interested_domains)
            VALUES ('$project_id', '$project_name', '$project_description', '$keywords', '$year_and_batch', 'in_progress', '$git_repo_link', FALSE, '$interested_domains')";
    
    if ($conn->query($sql) === TRUE) {
        // Update the user's role to group_leader and set in_group to TRUE
        $update_user_sql = "UPDATE user SET role = 'group_leader', in_group = TRUE WHERE id = '$username'";
        $conn->query($update_user_sql);
        
        // Insert the user into the user_project table
        $user_project_sql = "INSERT INTO user_project (project_id, user_id) VALUES ('$project_id', '$username')";
        $conn->query($user_project_sql);

        // Redirect to the student_in_group.php page after successful project creation
        header("Location: student_in_group.php");
        exit();
    } else {
        $error_message = "Error: " . $conn->error;
    }
}

// Handle invitation acceptance
if (isset($_POST['accept_invitation'])) {
    $project_id = $_POST['project_id']; // Get the project ID from the form

    // Update the user's `in_group` status
    $update_user_sql = "UPDATE user SET in_group = TRUE WHERE id = '$username'";
    $conn->query($update_user_sql);

    // Insert the user into the `user_project` table
    $user_project_sql = "INSERT INTO user_project (project_id, user_id) VALUES ('$project_id', '$username')";
    $conn->query($user_project_sql);

    // Remove the invitation from the `group_invite` table
    $delete_invite_sql = "DELETE FROM group_invite WHERE user_id = '$username' AND project_id = '$project_id'";
    $conn->query($delete_invite_sql);

    // Redirect the user to the student_in_group.php page
    header("Location: student_in_group.php");
    exit();
}



// Fetch pending invitations for the user
$invitation_query = "SELECT p.id AS project_id, p.name, p.description, u.name AS leader_name 
                     FROM group_invite gi
                     JOIN project p ON gi.project_id = p.id
                     JOIN user u ON u.id = (SELECT user_id FROM user_project WHERE project_id = gi.project_id LIMIT 1)
                     WHERE gi.user_id = '$username'";

$invitation_result = $conn->query($invitation_query);

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Not in Group</title>
</head>
<body>
    <h1>Welcome, <?php echo $user['name']; ?></h1>
    <h2>You are not part of a group yet.</h2>

    <!-- View invitations -->
    <h3>Group Invitations</h3>
    <?php if ($invitation_result->num_rows > 0): ?>
        <ul>
            <?php while ($invitation = $invitation_result->fetch_assoc()): ?>
                <li>
                    Group: <?php echo $invitation['name']; ?><br>
                    Description: <?php echo $invitation['description']; ?><br>
                    Leader: <?php echo $invitation['leader_name']; ?><br>

                    <!-- Accept invitation form -->
                    <form action="student_not_in_group.php" method="POST">
                        <input type="hidden" name="project_id" value="<?php echo $invitation['project_id']; ?>" >
                        <button type="submit" name="accept_invitation">Accept Invitation</button>
                    </form>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>No invitations yet.</p>
    <?php endif; ?>


    <!-- Button to open the project creation form -->
    <h3>Create a New Group</h3>
    <button onclick="document.getElementById('createGroupForm').style.display='block'">Create New Group</button>

    <!-- Project Creation Form -->
    <div id="createGroupForm" style="display:none;">
        <h4>Enter Project Details</h4>
        <form action="student_not_in_group.php" method="POST">
            <label for="project_name">Project Name:</label><br>
            <input type="text" name="project_name" required><br><br>

            <label for="project_description">Project Description:</label><br>
            <textarea name="project_description" required></textarea><br><br>

            <label for="year_and_batch">Year and Batch:</label><br>
            <input type="text" name="year_and_batch" required><br><br>

            <label for="keywords">Keywords (comma separated):</label><br>
            <input type="text" name="keywords"><br><br>

            <label for="git_repo_link">Git Repository Link:</label><br>
            <input type="url" name="git_repo_link"><br><br>

            <label for="interested_domains">Interested Domains (comma separated):</label><br>
            <input type="text" name="interested_domains"><br><br>

            <input type="submit" name="create_group" value="Create Group">
        </form>
    </div>

    <!-- Logout Button -->
    <a href="../logout.php"><button>Logout</button></a>
</body>
</html>
