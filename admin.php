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
    $role = $_POST['role'];

    // 'in_group' is untouched during updates
    $sql = "UPDATE user SET name='$name', role='$role' WHERE id='$id'";
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
</head>
<body>

<h2>Admin Dashboard</h2>

<!-- Switch to Mentor Mode -->
<?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'mentor'): ?>
    <form method="POST" action="admin/switch_to_mentor.php" style="display:inline;">
        <button type="submit">Switch to Mentor Mode</button>
    </form>
<?php endif; ?>

<div class="section-container">
        <h3>Upload a PDF</h3>
        <a href="pdf_upload/upload_single_pdf.php">
            <button>Go to PDF Upload</button>
        </a>
    </div>

<!-- Create User Form -->
<h3>Create User</h3>
<form method="POST">
    ID: <input type="text" name="id" required>
    Password: <input type="password" name="password" required>
    Name: <input type="text" name="name" required>
    Role: 
    <select name="role">
        <option value="admin">Admin</option>
        <option value="mentor">Mentor</option>
        <option value="student">Student</option>
        <option value="group_leader">Group Leader</option>
    </select>
    <button type="submit" name="create_user">Create User</button>
</form>

<!-- User List with Edit/Delete -->
<h3>User List</h3>
<table border="1">
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
        <td><?php echo $row['name']; ?></td>
        <td><?php echo $row['role']; ?></td>
        <td><?php echo $row['in_group'] ? 'Yes' : 'No'; ?></td>
        <td>
            <!-- Edit Form -->
            <form method="POST" style="display:inline;">
                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                Name: <input type="text" name="name" value="<?php echo $row['name']; ?>" required>
                Role:
                <select name="role">
                    <option value="admin" <?php if ($row['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                    <option value="mentor" <?php if ($row['role'] == 'mentor') echo 'selected'; ?>>Mentor</option>
                    <option value="student" <?php if ($row['role'] == 'student') echo 'selected'; ?>>Student</option>
                    <option value="group_leader" <?php if ($row['role'] == 'group_leader') echo 'selected'; ?>>Group Leader</option>
                </select>
                <button type="submit" name="edit_user">Edit</button>
            </form>

            <!-- Delete Form -->
            <form method="POST" style="display:inline;">
                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                <button type="submit" name="delete_user" onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<!-- Logout Button -->
<a href="logout.php">Logout</a>

</body>
</html>
