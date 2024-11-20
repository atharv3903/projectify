<?php
session_start();
include 'db.php'; // Include the database connection file

// Check if the user is an admin, if not, redirect to the login page
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Admin functionalities: List all users, projects, and other management features
$sql_users = "SELECT id, name, role FROM users";
$users_result = $conn->query($sql_users);

$sql_projects = "SELECT id, project_name, description, status FROM project";
$projects_result = $conn->query($sql_projects);

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id_to_delete = $_POST['user_id_to_delete'];
    $sql_delete_user = "DELETE FROM users WHERE id='$user_id_to_delete'";
    
    if ($conn->query($sql_delete_user) === TRUE) {
        $delete_message = "User deleted successfully!";
    } else {
        $delete_message = "Error deleting user: " . $conn->error;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
</head>
<body>

<h2>Admin Dashboard</h2>

<!-- Display users list -->
<h3>Users:</h3>
<?php if ($users_result->num_rows > 0): ?>
    <ul>
        <?php while ($user = $users_result->fetch_assoc()): ?>
            <li>
                <b><?php echo $user['name']; ?></b> (<?php echo $user['role']; ?>)
                <form action="admin.php" method="POST" style="display:inline;">
                    <input type="hidden" name="user_id_to_delete" value="<?php echo $user['id']; ?>">
                    <input type="submit" name="delete_user" value="Delete User">
                </form>
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>No users found.</p>
<?php endif; ?>

<!-- Display projects list -->
<h3>Projects:</h3>
<?php if ($projects_result->num_rows > 0): ?>
    <ul>
        <?php while ($project = $projects_result->fetch_assoc()): ?>
            <li>
                <b><?php echo $project['project_name']; ?></b> - Status: <?php echo $project['status']; ?> - Description: <?php echo $project['description']; ?>
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>No projects found.</p>
<?php endif; ?>

<!-- Logout Button -->
<a href="logout.php">Logout</a>

</body>
</html>
