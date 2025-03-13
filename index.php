<?php
session_start();
include 'db.php'; // Include the database connection file

// If the user is already logged in, redirect to their respective page
if (isset($_SESSION['username'])) {
    if ($_SESSION['role'] == 'student') {
        header("Location: student/index.php");
        exit();
    } elseif ($_SESSION['role'] == 'mentor') {
        header("Location: mentor.php");
        exit();
    } elseif ($_SESSION['role'] == 'group_leader') {
        header("Location: student/index.php");  // Redirect to student.php as group_leader also has student functionality
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
    $sql = "SELECT * FROM user WHERE id='$user_id'";  // Corrected table name
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if the password matches (we will later use password_verify for hashed passwords)
        if ($password === $user['password']) {
            // Set session variables
            $_SESSION['username'] = $user_id;
            $_SESSION['role'] = $user['role'];

            // Fetch mentor's skills if the user is a mentor
            if ($user['role'] == 'mentor') {
                $skills_sql = "SELECT expertise FROM mentor_skills WHERE mentor_id='$user_id'";
                $skills_result = $conn->query($skills_sql);
                $skills = [];
                while ($row = $skills_result->fetch_assoc()) {
                    $skills[] = $row['expertise'];
                }
                $_SESSION['skills'] = $skills;  // Save skills in session
            }

            // Redirect to the respective page based on the role
            if ($user['role'] == 'student') {
                header("Location: student/index.php");
            } elseif ($user['role'] == 'mentor') {
                header("Location: mentor.php");
            } elseif ($user['role'] == 'group_leader') {
                header("Location: student/index.php");  // Group leaders have student-like functionality
            }
        } else {
            $error_message = "Invalid credentials!";
        }
    } else {
        $error_message = "User not found!";
    }
}
///////////////////////////////////
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


</body>
</html>
