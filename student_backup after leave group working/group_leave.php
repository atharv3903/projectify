<?php
session_start();
include '../db.php'; // Include the database connection file

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php"); // Redirect to login page if not logged in
    exit();
}

// Get the user ID
$user_id = $_SESSION['username'];

// Check if the form has been submitted to leave the group
if (isset($_POST['leave_group'])) {
    // Check if the user is a group leader
    $query = "SELECT * FROM user WHERE id = '$user_id'";
    $result = $conn->query($query);
    $user = $result->fetch_assoc();

    // Check if the user is a group leader
    if ($user['role'] == 'group_leader') {
        // Fetch the project ID for the group leader (no need for 'role' in the 'user_project' table)
        $project_query = "SELECT p.id FROM project p 
                          JOIN user_project up ON p.id = up.project_id 
                          WHERE up.user_id = '$user_id'";
        $project_result = $conn->query($project_query);
        $project = $project_result->fetch_assoc();

        // Set in_group to false for all users in the project (including the leader) FIRST
        $update_group_status_query = "UPDATE user SET in_group = FALSE WHERE id IN (SELECT user_id FROM user_project WHERE project_id = '{$project['id']}')";
        $conn->query($update_group_status_query);

        // Delete all group members from user_project
        $delete_members_query = "DELETE FROM user_project WHERE project_id = '{$project['id']}'";
        $conn->query($delete_members_query);

        // Remove the group leader from user_project
        $delete_leader_query = "DELETE FROM user_project WHERE user_id = '$user_id' AND project_id = '{$project['id']}'";
        $conn->query($delete_leader_query);

        // Remove all invites for the project
        $delete_invites_query = "DELETE FROM group_invite WHERE project_id = '{$project['id']}'";
        $conn->query($delete_invites_query);

        // Change the group leader's role to 'student'
        $update_role_query = "UPDATE user SET role = 'student' WHERE id = '$user_id'";
        $conn->query($update_role_query);

        // Optionally delete the project itself if the leader is leaving
        // You can add a condition to delete the project only if there are no members left.
        //$check_members_query = "SELECT COUNT(*) AS total_members FROM user_project WHERE project_id = '{$project['id']}'";
        //$members_result = $conn->query($check_members_query);
        //$members = $members_result->fetch_assoc();


        // Delete the project since the group leader left
        $delete_project_query = "DELETE FROM project WHERE id = '{$project['id']}'";
        $conn->query($delete_project_query);

    } else {
        // If the user is not a group leader, just remove them from the user_project table
        $project_query = "SELECT p.id FROM project p 
                          JOIN user_project up ON p.id = up.project_id 
                          WHERE up.user_id = '$user_id'";
        $project_result = $conn->query($project_query);
        $project = $project_result->fetch_assoc();

        // Set in_group to false for the user FIRST
        $update_group_status_query = "UPDATE user SET in_group = FALSE WHERE id = '$user_id'";
        $conn->query($update_group_status_query);

        // Remove the user from the user_project table
        $delete_member_query = "DELETE FROM user_project WHERE user_id = '$user_id' AND project_id = '{$project['id']}'";
        $conn->query($delete_member_query);
    }

    // Redirect to the "not in group" page
    header("Location: student_not_in_group.php");
    exit();
}
?>
