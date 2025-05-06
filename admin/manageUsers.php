<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/projectify/db.php';

// Check if the user is an admin
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Function to generate a unique random ID
function generateUniqueID($conn) {
    do {
        $randomID = rand(10000, 99999); // Generates a 5-digit random number
        $checkQuery = "SELECT id FROM user WHERE id = '$randomID'";
        $result = $conn->query($checkQuery);
    } while ($result->num_rows > 0); // Repeat until a unique ID is found

    return $randomID;
}

// Handle Create User
// Handle Create User
if (isset($_POST['create_user'])) {
    // ID Handling: Use provided ID or generate one
    $id = !empty($_POST['id']) ? trim($_POST['id']) : generateUniqueID2222($conn);

    // Password Handling: Use provided password or default to '123'
    $password = !empty($_POST['password']) ? $_POST['password'] : '123';

    $name = $_POST['name'];
    $role = $_POST['role'];

    // Check if ID already exists
    $checkQuery = "SELECT id FROM user WHERE id = '$id'";
    $result = $conn->query($checkQuery);

    if ($result->num_rows > 0) {
        echo "<p class='error-message'>Error: ID '$id' already exists. Please choose a different ID.</p>";
    } else {
        // Insert the new user
        $sql = "INSERT INTO user (id, password, name, role, in_group) 
                VALUES ('$id', '$password', '$name', '$role', 0)";
        
        if ($conn->query($sql)) {
            echo "<p class='success-message'>User created successfully with ID: $id</p>";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "<p class='error-message'>Error creating user: " . $conn->error . "</p>";
        }
    }
}

// Function to generate a unique random ID
function generateUniqueID2222($conn) {
    do {
        $randomID = rand(10000, 99999); // Generates a 5-digit random number
        $checkQuery = "SELECT id FROM user WHERE id = '$randomID'";
        $result = $conn->query($checkQuery);
    } while ($result->num_rows > 0);

    return $randomID;
}



// Handle Edit User
if (isset($_POST['edit_user'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];

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

include 'navbar.php';

// Handle CSV Upload
if (isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $csvFile = fopen($_FILES['csv_file']['tmp_name'], 'r');

        // Skip the first line (header)
        fgetcsv($csvFile);

        // Process each row
        while (($row = fgetcsv($csvFile)) !== false) {
            $id = !empty($row[0]) ? $row[0] : generateUniqueID($conn); // Generate ID if empty
            $name = htmlspecialchars(trim($row[1]));
            $role = strtolower(trim($row[2]));

            // Validate role
            $valid_roles = ['admin', 'mentor', 'student'];
            if (!in_array($role, $valid_roles)) {
                echo "<p class='error-message'>Invalid role for user: $name</p>";
                continue;
            }

            // Check for duplicate ID
            $checkQuery = "SELECT id FROM user WHERE id = '$id'";
            $result = $conn->query($checkQuery);

            if ($result->num_rows > 0) {
                echo "<p class='error-message'>Duplicate ID detected for user: $name. Skipping.</p>";
                continue;
            }

            // Insert user into database
            $sql = "INSERT INTO user (id, password, name, role, in_group) 
                    VALUES ('$id', '123', '$name', '$role', 0)";
            
            if (!$conn->query($sql)) {
                echo "<p class='error-message'>Error adding user: $name — " . $conn->error . "</p>";
            } else {
                echo "<p class='success-message'>User '$name' added successfully.</p>";
            }
        }

        fclose($csvFile);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit(); // Ensures no further code runs after the redirect
    } else {
        echo "<p class='error-message'>Please upload a valid CSV file.</p>";
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="admin/styles.css">
</head>
<body>

<div class="navbar">    
    <h2>Projectify</h2>
    <a href="../index.php">Home</a>
    <a href="../all_projects.php">View All Projects</a>
    <a href="switch_to_mentor.php">Mentor mode</a>
    <a href="match_mentors.php">Match Mentors</a>
    <a href="manageUsers.php">Manage Users</a>
    <a href="../logout.php">Logout</a>
</div>

<h1>Manage Users</h1>

<!-- Create User Section -->
<div class="section-container">
    <h3>Create User</h3>
    <form method="POST">
        
        <label>ID (Optional):</label>
        <input type="text" name="id">

        <label>Name:</label>
        <input type="text" name="name" required>
        
        <label>Password (Optional, defaults to 123):</label>
        <input type="password" name="password">
        
        <label>Role:</label>
        <select name="role">
            <option value="admin">Admin</option>
            <option value="mentor">Mentor</option>
            <option value="student">Student</option>
        </select>

        <button type="submit" name="create_user">Create User</button>
    </form>
</div>



<!-- CSV Import Section -->
<div class="section-container">
    <h3>Import Users from CSV</h3>
    <form method="POST" enctype="multipart/form-data">
        <label for="csv_file">Upload CSV File:</label>
        <input type="file" name="csv_file" accept=".csv" required>
        <button type="submit" name="import_csv">Import Users</button>
    </form>
</div>


<!-- User List Section -->
<div class="section-container">
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
                <!-- Edit Form -->
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                    <input type="text" name="name" value="<?php echo htmlspecialchars($row['name']); ?>" required>
                    <button type="submit" name="edit_user">Edit</button>
                </form>

                <!-- Delete Form -->
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
</div>

<!-- Back to Admin Dashboard -->
<div class="section-container">
    <a href="admin_dashboard.php" class="button">Back to Dashboard</a>
</div>

</body>
</html>
