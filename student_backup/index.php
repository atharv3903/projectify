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

    // Redirect to appropriate page based on in_group status
    if ($in_group) {
        header("Location: student_in_group.php");
        exit();
    } else {
        header("Location: student_not_in_group.php");
        exit();
    }
    ?>
