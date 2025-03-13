<?php
session_start();
include 'db.php'; // Include the database connection file

// Check if the user is logged in and is a mentor
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'mentor') {
    header("Location: login.php"); // Redirect to login if not a mentor
    exit();
}

$username = $_SESSION['username'];

// Fetch mentor's allocated groups
$sql = "SELECT 
            p.id AS project_id, 
            p.name AS project_name, 
            p.description, 
            p.status, 
            p.year_and_batch 
        FROM project p
        INNER JOIN user_project up ON p.id = up.project_id
        WHERE up.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor Dashboard</title>
    <link rel="stylesheet" href="mentorStyles.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f2f5;
            color: #333;
        }
        .navbar {
            background-color: #343a40;
            color: #fff;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .navbar a {
            color: #fff;
            text-decoration: none;
            margin-right: 20px;
            font-weight: bold;
        }
        .profile {
            margin: 20px;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .my-groups {
            margin: 20px;
        }
        .group {
            background-color: #fff;
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .group h3 {
            margin-bottom: 10px;
            color: #343a40;
        }
        .group p {
            color: #555;
        }
        .view-details {
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 15px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            text-align: center;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</div>
        <div>
            <a href="mentor/mentor_profile.php?mentor_id=<?php echo $username; ?>" class="btn">View Profile</a>
            <a href="logout.php" class="btn">Logout</a>
        </div>
    </div>

    <?php
        // Add "Switch to Admin Mode" button for users who are admins
        if ($_SESSION['original_role'] == 'admin') {
            echo '
            <form method="POST" action="mentor/switch_to_admin.php" style="display:inline;">
                <button type="submit">Switch to Admin Mode</button>
            </form>';
        }
    ?>

    

    <!-- Profile Section -->
    <div class="profile">
        <h2>Mentor Profile</h2>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        <p><strong>Role:</strong> Mentor</p>
        <a href="mentor/mentor_profile.php?mentor_id=<?php echo $username; ?>" class="btn">Edit Profile</a>
    </div>

    <!-- My Groups Section -->
    <div class="my-groups">
        <h2>My Groups</h2>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="group">
                    <h3><?php echo htmlspecialchars($row['project_name']); ?></h3>
                    <p><strong>Project ID:</strong> <?php echo htmlspecialchars($row['project_id']); ?></p>
                    <p><strong>Batch:</strong> <?php echo htmlspecialchars($row['year_and_batch']); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($row['description']); ?></p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($row['status']); ?></p>
                    <a href="mentor/group_details.php?project_id=<?php echo $row['project_id']; ?>" class="view-details">View Details</a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No groups allocated yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>