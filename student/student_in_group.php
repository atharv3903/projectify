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

// Ensure the user is in a group
if (!$user['in_group']) {
    echo "You are not part of a group.";
    header("Location: index.php");
    exit();
}

// Fetch the project information
$project_query = "SELECT p.* FROM project p 
                  JOIN user_project up ON p.id = up.project_id 
                  WHERE up.user_id  = '$username'";
$project_result = $conn->query($project_query);
$project = $project_result->fetch_assoc();


// Check if the project is frozen
if ($project['frozen']) {
    // If the project is frozen, show frozen status and stop further actions
    header("Location: student_frozen.php"); // Redirect to the frozen dashboard
    exit();
}


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
                $_SESSION['success_message'] = "Student invited successfully!";
            } else {
                $_SESSION['error_message'] = "Error inviting student: " . $conn->error;
            }
        } else {
            $_SESSION['error_message'] = "This student has already been invited to the group.";
        }
    } else {
        $_SESSION['error_message'] = "Student does not exist, is not a valid student, or is already in a group.";
    }

    // Redirect to the same page to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


// Handle member removal by group leader
if (isset($_POST['remove_member'])) {
    $remove_member_id = $_POST['remove_member_id'];
    $project_id = $project['id'];

    // Check if the member exists in the group
    $member_check_query = "SELECT * FROM user_project WHERE user_id = '$remove_member_id' AND project_id = '$project_id'";
    $member_check_result = $conn->query($member_check_query);

    if ($member_check_result->num_rows > 0) {
        // Remove the member from the user_project table
        $delete_member_query = "DELETE FROM user_project WHERE user_id = '$remove_member_id' AND project_id = '$project_id'";
        $conn->query($delete_member_query);

        // Set the user's in_group to FALSE
        $update_user_status_query = "UPDATE user SET in_group = FALSE WHERE id = '$remove_member_id'";
        $conn->query($update_user_status_query);

        $success_message = "Member removed successfully!";
    } else {
        $error_message = "Member not found in the group.";
    }
}


// Fetch the list of invited students
$invited_query = "SELECT u.id, u.name FROM user u 
                  JOIN group_invite gi ON u.id = gi.user_id 
                  WHERE gi.project_id = '{$project['id']}'";
$invited_result = $conn->query($invited_query);
$leave_message = $is_leader ? "Are you sure? If you leave, the entire group will be deleted." : "Are you sure you want to leave the group?";



// Display success or error messages
if (isset($_SESSION['success_message'])) {
    echo "<p style='color:green;'>" . $_SESSION['success_message'] . "</p>";
    unset($_SESSION['success_message']); // Clear the message after showing it
}

if (isset($_SESSION['error_message'])) {
    echo "<p style='color:red;'>" . $_SESSION['error_message'] . "</p>";
    unset($_SESSION['error_message']); // Clear the message after showing it
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - In Group</title>
    <link rel="stylesheet" href="styles.css">
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
        // Display success or error messages
        if (isset($_SESSION['success_message'])) {
            echo "<p style='color:green;'>" . $_SESSION['success_message'] . "</p>";
            unset($_SESSION['success_message']);
        }

        if (isset($_SESSION['error_message'])) {
            echo "<p style='color:red;'>" . $_SESSION['error_message'] . "</p>";
            unset($_SESSION['error_message']);
        }
        ?>

        
    <?php else: ?>
        <h3>You are a member of this group.</h3>
    <?php endif; ?>

    <h3>Invited Students in the group: </h3>
        <ul>
            <?php while ($invited = $invited_result->fetch_assoc()): ?>
                <li><?php echo $invited['name'] . " (ID: " . $invited['id'] . ")"; ?></li>
            <?php endwhile; ?>
        </ul>

        <h3>Group Members</h3>
    <ul>
        <?php 
        // Fetch group members
        $members_query = "SELECT u.id, u.name FROM user u 
                          JOIN user_project up ON u.id = up.user_id 
                          WHERE up.project_id = '{$project['id']}'";
        $members_result = $conn->query($members_query);

        while ($member = $members_result->fetch_assoc()): 
        ?>
            <li>
                <?php echo $member['name'] . " (ID: " . $member['id'] . ")"; ?>
                <?php if ($is_leader && $member['id'] != $username): ?>
                    <!-- Remove button for group leader -->
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="remove_member_id" value="<?php echo $member['id']; ?>">
                        <button type="submit" name="remove_member" style="color: red;">Remove</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endwhile; ?>
    </ul>



    <a href="../logout.php"><button>Logout</button></a>

    <!-- Common Leave Group Button -->
    <form method="POST" action="group_leave.php" onsubmit="return confirm('<?php echo $leave_message; ?>')">
        <button type="submit" name="leave_group" style="color: red;">Leave Group</button>
    </form>

</body>
</html>
