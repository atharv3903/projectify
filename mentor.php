<?php 
session_start();
include 'db.php'; // Include the database connection file

// Check if the user is logged in and is a mentor
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'mentor') {
    header("Location: index.php"); // Redirect to login if not a mentor
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
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        /* General Styles */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f6f9;
            color: #333;
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding-top: 20px;
            position: fixed;
            left: 0;
            top: 0;
            transition: all 0.3s ease-in-out;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
        }

        .sidebar h2 {
            text-align: center;
            font-size: 22px;
            margin-bottom: 20px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            font-size: 16px;
            transition: background 0.3s ease-in-out;
        }

        .sidebar a:hover, .sidebar a.active {
            background: #2980b9;
            padding-left: 25px;
        }

        .sidebar i {
            margin-right: 10px;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            width: calc(100% - 260px);
            transition: all 0.3s ease-in-out;
        }

        /* Navbar */
        .navbar {
            background-color: white;
            color: #333;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #ddd;
            border-radius: 8px;
        }

        .navbar .user {
            font-weight: 600;
            font-size: 18px;
        }

        /* Cards */
        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .card h3 {
            margin-bottom: 10px;
            color: #007bff;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 15px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: #fff;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease-in-out;
        }

        .btn:hover {
            background: linear-gradient(135deg, #2980b9, #1a5276);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 220px;
            }

            .main-content {
                margin-left: 220px;
                width: calc(100% - 220px);
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Mentor Dashboard</h2>
        <a href="mentor_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="mentor/mentor_profile.php?mentor_id=<?php echo $username; ?>"><i class="fas fa-user"></i> Profile</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php if ($_SESSION['original_role'] == 'admin'): ?>
            <a href="mentor/switch_to_admin.php" class="btn"><i class="fas fa-user-shield"></i> Switch to Admin</a>
        <?php endif; ?>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="navbar">
            <div class="user">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</div>
        </div>

        <!-- Profile Section -->
        <div class="card">
            <h2>Mentor Profile</h2>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            <p><strong>Role:</strong> Mentor</p>
            <a href="mentor/mentor_profile.php?mentor_id=<?php echo $username; ?>" class="btn">Edit Profile</a>
        </div>

        <!-- My Groups Section -->
        <div class="card">
            <h2>My Groups</h2>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="card">
                        <h3><?php echo htmlspecialchars($row['project_name']); ?></h3>
                        <p><strong>Project ID:</strong> <?php echo htmlspecialchars($row['project_id']); ?></p>
                        <p><strong>Batch:</strong> <?php echo htmlspecialchars($row['year_and_batch']); ?></p>
                        <p><strong>Description:</strong> <?php echo htmlspecialchars($row['description']); ?></p>
                        <p><strong>Status:</strong> <?php echo htmlspecialchars($row['status']); ?></p>
                        <a href="mentor/group_details.php?project_id=<?php echo $row['project_id']; ?>" class="btn">View Details</a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No groups allocated yet.</p>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>