<?php
session_start();
include '../db.php'; // Include the database connection file
include 'navbar_notFrozen.php'; // Include the navbar

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
    echo "You are already in a group.";
    header("Location: index..php");
    exit();
}

// Calculate Current Financial Year
$current_year = (date('m') >= 4) ? date('Y') . '-' . (date('Y') + 1) : (date('Y') - 1) . '-' . date('Y');

// Check if the form is submitted to create a new group
if (isset($_POST['create_group'])) {
    // Get the form data
    $branch = $_POST['branch'];
    $year_and_batch = $current_year; // Automatically set to the calculated financial year
    $year_and_batch = $current_year . " " . $branch;


    $project_name = substr($_POST['project_name'], 0, 50);  // Max 50 chars
    $project_description = substr($_POST['project_description'], 0, 250); // Max 250 chars
    $git_repo_link = substr($_POST['git_repo_link'], 0, 250); // Max 250 chars
    $interested_domains = substr($_POST['interested_domains'], 0, 250); // Max 250 chars
    $keywords = $_POST['keywords'];

    // Create a new project ID
    $project_id = uniqid('proj_', true);

    // Insert the new project into the project table
    $sql = "INSERT INTO project (id, name, description, keywords, year_and_batch, status, git_repo_link, frozen, interested_domains)
            VALUES ('$project_id', '$project_name', '$project_description', '$keywords', '$year_and_batch', 'in_progress', '$git_repo_link', FALSE, '$interested_domains')";

    if ($conn->query($sql) === TRUE) {
        // Update user's role and set in_group to TRUE
        $update_user_sql = "UPDATE user SET role = 'group_leader', in_group = TRUE WHERE id = '$username'";
        $conn->query($update_user_sql);

        // Insert user into the user_project table
        $user_project_sql = "INSERT INTO user_project (project_id, user_id) VALUES ('$project_id', '$username')";
        $conn->query($user_project_sql);

        // Redirect after successful project creation
        header("Location: student_in_group.php");
        exit();
    } else {
        $error_message = "Error: " . $conn->error;
    }
}


// Handle invitation acceptance
if (isset($_POST['accept_invitation'])) {
    $project_id = $_POST['project_id']; // Get the project ID from the form

    // Get the maximum group size from the setting table
    $max_group_size_result = $conn->query("SELECT max_group_size FROM setting LIMIT 1");
    $max_group_size_row = $max_group_size_result->fetch_assoc();
    $max_group_size = $max_group_size_row['max_group_size'];

    // Count the current number of users in the group
    //$current_group_size_result = $conn->query("SELECT COUNT(*) AS group_size FROM user_project WHERE project_id = '$project_id'");
    $current_group_size_result = $conn->query("SELECT COUNT(*) AS group_size FROM user_project
                                           JOIN user ON user_project.user_id = user.id
                                           WHERE user_project.project_id = '$project_id' 
                                           AND user.role IN ('student', 'group_leader')");

    $current_group_size_row = $current_group_size_result->fetch_assoc();
    $current_group_size = $current_group_size_row['group_size'];

    // Check if the group is full
    if ($current_group_size >= $max_group_size) {
        // Group is full, remove the invitation
        $delete_invite_sql = "DELETE FROM group_invite WHERE user_id = '$username' AND project_id = '$project_id'";
        $conn->query($delete_invite_sql);

        // Display an error message to the user (or set a variable to show it in the UI)
        $error_message = "The group is already full. You cannot join this group.";

        // Optional: Redirect back to the invitations page with an error message
        echo "<p style='color:red;'> The group is already full. You cannot join this group. </p>";
        //header("Location: invitations.php?error=" . urlencode($error_message));
        exit();
    }

    // If the group is not full, proceed with the invitation acceptance
    $update_user_sql = "UPDATE user SET in_group = TRUE WHERE id = '$username'";
    $conn->query($update_user_sql);

    $user_project_sql = "INSERT INTO user_project (project_id, user_id) VALUES ('$project_id', '$username')";
    $conn->query($user_project_sql);

    $delete_invite_sql = "DELETE FROM group_invite WHERE user_id = '$username' AND project_id = '$project_id'";
    $conn->query($delete_invite_sql);

    // Redirect the user to the student_in_group.php page
    header("Location: student_in_group.php");
    exit();
}


// Handle invitation rejection
if (isset($_POST['reject_invitation'])) {
    $project_id = $_POST['project_id']; // Get the project ID from the form

    // Remove the invitation from the group_invite table
    $delete_invite_sql = "DELETE FROM group_invite WHERE user_id = '$username' AND project_id = '$project_id'";
    if ($conn->query($delete_invite_sql) === TRUE) {
        // Optional: Add a success message
        $_SESSION['success_message'] = "Invitation successfully rejected.";
    } else {
        // Optional: Add an error message
        $_SESSION['error_message'] = "Error rejecting invitation: " . $conn->error;
    }

    // Redirect to avoid form resubmission
    header("Location: student_not_in_group.php");
    exit();
}


if (isset($_SESSION['success_message'])) {
    echo "<p style='color:green;'>" . $_SESSION['success_message'] . "</p>";
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    echo "<p style='color:red;'>" . $_SESSION['error_message'] . "</p>";
    unset($_SESSION['error_message']);
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
    <link rel="stylesheet" href="styles.css">


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

                    <!-- Reject invitation form -->
                    <form action="student_not_in_group.php" method="POST" style="display:inline;">
                        <input type="hidden" name="project_id" value="<?php echo $invitation['project_id']; ?>" >
                        <button type="submit" name="reject_invitation">Reject Invitation</button>
                    </form>

                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>No invitations yet.</p>
    <?php endif; ?>


    <!-- Button to open the project creation form -->
    <!-- <h3>Create a New Group</h3> -->
    <button onclick="document.getElementById('createGroupForm').style.display='block'">Create New Group</button>

   <!-- Project Creation Form -->
   <div id="createGroupForm" style="display:none;">
    <h4>Enter Project Details</h4>
    <form action="student_not_in_group.php" method="POST">

        <label for="project_name">Project Name:</label><br>
        <input type="text" name="project_name" maxlength="50" required><br><br>

        <label for="project_description">Project Description:</label><br>
        <textarea name="project_description" maxlength="250" required></textarea><br><br>

        <label for="branch">Branch:</label><br>
        <select name="branch" required>
            <option value="CSE">CSE</option>
            <option value="ENTC">ENTC</option>
            <option value="Mechanical">Mechanical</option>
            <option value="CIVIL">CIVIL</option>
            <option value="Environment">Environment</option>
        </select><br><br>

        <label for="git_repo_link">Git Repository Link:</label><br>
        <input type="text" name="git_repo_link" maxlength="250"><br><br>

        <label for="interested_domains">Interested Domains (comma separated):</label><br>
        <input type="text" name="interested_domains" maxlength="250"><br><br>

        <input type="submit" name="create_group" value="Create Group">
    </form>
</div>



    <!-- Logout Button -->
    <a href="../logout.php"><button>Logout</button></a>
</body>
</html>
