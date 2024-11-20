<?php
session_start();
include 'db.php'; // Include the database connection file

// If the user is already logged in, redirect to their respective page
if (isset($_SESSION['username'])) {
    if ($_SESSION['role'] == 'student') {
        header("Location: student.php");
        exit();
    } elseif ($_SESSION['role'] == 'mentor') {
        header("Location: mentor.php");
        exit();
    } elseif ($_SESSION['role'] == 'group_leader') {
        header("Location: student.php");  // Redirect to student.php as group_leader also has student functionality
        exit();
    }
	elseif ($_SESSION['role'] == 'admin') {
        header("Location: admin.php");
        exit();
    }
}

// Handle login form submission
if (isset($_POST['login'])) {
    $user_id = $_POST['username'];  // Use 'id' as the login identifier
    $password = $_POST['password'];

    // Query to check if the user exists
    $sql = "SELECT * FROM users WHERE id='$user_id'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if the password matches (we will later use password_verify for hashed passwords)
        if ($password === $user['password']) {
            // Set session variables
            $_SESSION['username'] = $user_id;
            $_SESSION['role'] = $user['role'];

            // Redirect to the respective page based on the role
            if ($user['role'] == 'student') {
                header("Location: student.php");
            } elseif ($user['role'] == 'mentor') {
                header("Location: mentor.php");
            } elseif ($user['role'] == 'group_leader') {
                header("Location: student.php");  // Group leaders have student-like functionality
            }
        } else {
            $error_message = "Invalid credentials!";
        }
    } else {
        $error_message = "User not found!";
    }
}

// Handle registration form submission
if (isset($_POST['register'])) {
    $new_user_id = $_POST['new_username'];  // Use 'id' for registration
    $new_password = $_POST['new_password'];

    // Insert the new user into the database (you should hash the password for security)
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (id, password, role) VALUES ('$new_user_id', '$hashed_password', 'student')";

    if ($conn->query($sql) === TRUE) {
        $success_message = "Registration successful!";
    } else {
        $error_message = "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="styles.css">
    <title>Login</title>
</head>
<body>
    <h2>Login</h2>
    
    <?php if (isset($error_message)): ?>
        <p style="color:red;"><?php echo $error_message; ?></p>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <p style="color:green;"><?php echo $success_message; ?></p>
    <?php endif; ?>

    <!-- Login Form -->
    <form action="index.php" method="POST">
        <label for="username">User ID:</label>
        <input type="text" name="username" required><br><br>

        <label for="password">Password:</label>
        <input type="password" name="password" required><br><br>

        <input type="submit" name="login" value="Login">
    </form>

    <!-- Registration Form -->
    <form action="index.php" method="POST">
        <label for="new_username">New User ID:</label>
        <input type="text" name="new_username" required><br><br>

        <label for="new_password">New Password:</label>
        <input type="password" name="new_password" required><br><br>

        <input type="submit" name="register" value="Register">
    </form>
</body>
</html>
