<?php
session_start();
include 'db.php';

// Check if the user is an admin
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Handle Create User
if (isset($_POST['create_user'])) {
    $id = $_POST['id'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT); // Encrypt password
    $password = $_POST['password'];
    $name = $_POST['name'];
    $role = $_POST['role'];

    // 'in_group' is always set to 0 by default
    $sql = "INSERT INTO user (id, password, name, role, in_group) 
            VALUES ('$id', '$password', '$name', '$role', 0)";
    $conn->query($sql);
}

// Handle Edit User
if (isset($_POST['edit_user'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    //$role = $_POST['role'];

    // 'in_group' is untouched during updates
    // $sql = "UPDATE user SET name='$name', role='$role' WHERE id='$id'";
    $sql = "UPDATE user SET name='$name' WHERE id='$id'";
    $conn->query($sql);
}

// Handle Delete User
if (isset($_POST['delete_user'])) {
    $id = $_POST['id'];
    $sql = "DELETE FROM user WHERE id='$id'";
    $conn->query($sql);
}

// Fetch all users
$users = $conn->query("SELECT * FROM user");





?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin/styles.css">
</head>
<body>

<div class="navbar">    
    <h2>Projectify</h2>
    <a href="index.php">Home</a>
    <a href="all_projects.php">View All Projects</a>
    <a href="admin/switch_to_mentor.php">Mentor mode</a>
    <a href="admin/match_mentors.php">Match Mentors</a>
    <a href="admin/manageUsers.php">Manage Users</a>
    <a href="social.php"><i class="fas fa-users"></i> Social Feed</a>
    <a href="logout.php">Logout</a>
</div>


    <h1>Admin Dashboard</h1>

    <!-- Switch to Mentor Mode -->
    <!-- <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'mentor'): ?>
        <div class="section-container">
            <form method="POST" action="admin/switch_to_mentor.php" style="display:inline;">
                <button type="submit">Switch to Mentor Mode</button>
            </form>
        </div>
    <?php endif; ?> -->

    <!-- PDF Upload Section -->
    <!-- <div class="section-container">
        <h3>Upload a PDF</h3>
        <a href="pdf_upload/upload_single_pdf.php" class="button">Go to PDF Upload</a>
    </div> -->

    <!-- Create User Section -->
    <!-- <div class="section-container">
        <h3>Create User</h3>
        <form method="POST">
            <label>ID:</label>
            <input type="text" name="id" required>

            <label>Password:</label>
            <input type="password" name="password" required>

            <label>Name:</label>
            <input type="text" name="name" required>

            <label>Role:</label>
            <select name="role">
                <option value="admin">Admin</option>
                <option value="mentor">Mentor</option>
                <option value="student">Student</option>
                <option value="group_leader">Group Leader</option>
            </select>

            <button type="submit" name="create_user">Create User</button>
        </form>
    </div> -->

    <!-- User List Section -->
    <!-- <div class="section-container">
        <h3>User List</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Role</th>
                <th>In Group</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $users->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['role']); ?></td>
                <td><?php echo $row['in_group'] ? 'Yes' : 'No'; ?></td>
                <td>
                    
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <input type="text" name="name" value="<?php echo htmlspecialchars($row['name']); ?>" required>
                        <button type="submit" name="edit_user">Edit</button>
                    </form>

                    
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="delete_user" onclick="return confirm('Are you sure you want to delete this user?')">
                            Delete
                        </button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div> -->

    <!-- Logout Button -->
    <!-- <div class="section-container">
        <a href="logout.php" class="button">Logout</a>
    </div> -->
</body>
</html>
