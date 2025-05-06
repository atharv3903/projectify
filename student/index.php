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
        // If user is not found, log them out and redirect to login page
        header("Location: ../logout.php"); 
        exit();
    }

    $user = $result->fetch_assoc();
    $in_group = $user['in_group'];
    
    // Check if the user is part of a group
    if ($in_group) {
        // Check if the user is part of a frozen project
        $project_query = "SELECT p.frozen FROM project p
                          JOIN user_project up ON p.id = up.project_id
                          WHERE up.user_id = '$username'";
        $project_result = $conn->query($project_query);
    
        if ($project_result->num_rows > 0) {
            $project = $project_result->fetch_assoc();
            // If the project is frozen, redirect to student_frozen.php
            if ($project['frozen']) {
                header("Location: student_frozen.php");
                exit();
            }
        }
        
        // Redirect to student_in_group.php if the user is part of a group and project is not frozen
        header("Location: student_in_group.php");
        exit();
    
    } else {
        // Redirect to student_not_in_group.php if the user is not part of a group
        header("Location: student_not_in_group.php");
        exit();
    }
    ?>
