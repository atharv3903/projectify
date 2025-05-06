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

// Ensure the user is in a group
if (!$user['in_group']) {
    header("Location: student_not_in_group.php");
    exit();
}

// Fetch the project information
$project_query = "SELECT p.* FROM project p 
                  JOIN user_project up ON p.id = up.project_id 
                  WHERE up.user_id  = '$username'";
$project_result = $conn->query($project_query);
$project = $project_result->fetch_assoc();

// Check if the user is a group leader
$is_leader = $user['role'] === 'group_leader';

// Handle inviting a student
if (isset($_POST['invite_student'])) {
    $invitee_id = $_POST['invitee_id'];
    $project_id = $project['id'];

    // Check if the student exists, has role 'student', and is not in a group
    $invitee_query = "SELECT * FROM user 
                      WHERE id = '$invitee_id' 
                      AND role = 'student' 
                      AND in_group = FALSE";
    $invitee_result = $conn->query($invitee_query);

    if ($invitee_result->num_rows > 0) {
        // Check if already invited
        $check_invite_query = "SELECT * FROM group_invite 
                               WHERE user_id = '$invitee_id' AND project_id = '$project_id'";
        $check_invite_result = $conn->query($check_invite_query);

        if ($check_invite_result->num_rows == 0) {
            // Add to the group_invite table
            $invite_sql = "INSERT INTO group_invite (user_id, project_id) VALUES ('$invitee_id', '$project_id')";
            if ($conn->query($invite_sql) === TRUE) {
                $success_message = "Student invited successfully!";
            } else {
                $error_message = "Error inviting student: " . $conn->error;
            }
        } else {
            $error_message = "This student has already been invited to the group.";
        }
    } else {
        $error_message = "Student does not exist, is not a valid student, or is already in a group.";
    }
}

// Fetch the list of invited students
$invited_query = "SELECT u.id, u.name FROM user u 
                  JOIN group_invite gi ON u.id = gi.user_id 
                  WHERE gi.project_id = '{$project['id']}'";
$invited_result = $conn->query($invited_query);
$leave_message = $is_leader ? "Are you sure? If you leave, the entire group will be deleted." : "Are you sure you want to leave the group?";


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - In Group</title>
</head>
<body>
    <h1>Welcome, <?php echo $user['name']; ?></h1>
    <h2>You are part of the group: <?php echo $project['name']; ?></h2>

    <?php if ($is_leader): ?>
        <h3>Invite Students to Your Group</h3>
        <form method="POST">
            <label for="invitee_id">Student ID:</label>
            <input type="text" name="invitee_id" required>
            <button type="submit" name="invite_student">Invite</button>
        </form>

        <?php 
        if (isset($success_message)) {
            echo "<p style='color:green;'>$success_message</p>";
        }
        if (isset($error_message)) {
            echo "<p style='color:red;'>$error_message</p>";
        }
        ?>

        <h3>Invited Students</h3>
        <ul>
            <?php while ($invited = $invited_result->fetch_assoc()): ?>
                <li><?php echo $invited['name'] . " (ID: " . $invited['id'] . ")"; ?></li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <h3>You are a member of this group.</h3>
    <?php endif; ?>

    <a href="../logout.php"><button>Logout</button></a>

    <!-- Common Leave Group Button -->
    <form method="POST" action="group_leave.php" onsubmit="return confirm('<?php echo $leave_message; ?>')">
        <button type="submit" name="leave_group" style="color: red;">Leave Group</button>
    </form>

</body>
</html>
