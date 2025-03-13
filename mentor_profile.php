<?php
session_start();
include 'db_connect.php';

// Ensure the user is logged in and is a mentor
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'mentor') {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// Handle profile update (POST Request)
$update_success = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $updated_name = $_POST['name'];
    $updated_skills = $_POST['skills']; // Array of skills

    // Update mentor name
    $update_name_sql = "UPDATE user SET name = '$updated_name' WHERE id = '$username'";
    $conn->query($update_name_sql);

    // Clear old skills and insert new skills
    $conn->query("DELETE FROM mentor_skills WHERE mentor_id = '$username'");
    foreach ($updated_skills as $skill) {
        $skill = trim($skill); // Remove extra spaces
        if (!empty($skill)) {
            $insert_skill_sql = "INSERT INTO mentor_skills (mentor_id, expertise) VALUES ('$username', '$skill')";
            $conn->query($insert_skill_sql);
        }
    }

    $update_success = true;
}

// Fetch mentor details
$sql = "SELECT name FROM user WHERE id = '$username'";
$result = $conn->query($sql);
$mentor = $result->fetch_assoc();

// Fetch mentor's skillset
$skill_sql = "SELECT expertise FROM mentor_skills WHERE mentor_id = '$username'";
$skill_result = $conn->query($skill_sql);
$skills = [];
while ($row = $skill_result->fetch_assoc()) {
    $skills[] = $row['expertise'];
}

// Fetch completed projects
$project_sql = "SELECT name, description FROM project 
                INNER JOIN user_project ON project.id = user_project.project_id 
                WHERE user_project.user_id = '$username' AND project.status = 'completed'";
$project_result = $conn->query($project_sql);
$projects = [];
while ($row = $project_result->fetch_assoc()) {
    $projects[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentor Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .profile-container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #007bff;
        }
        .section {
            margin-bottom: 20px;
        }
        .section h3 {
            border-bottom: 2px solid #007bff;
            display: inline-block;
            margin-bottom: 10px;
        }
        .btn {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn:hover {
            background: #0056b3;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }
        .modal-content button {
            margin-top: 20px;
        }
        .success {
            color: green;
            font-weight: bold;
        }
    </style>
    <script>
        function toggleEditMode() {
            const viewSection = document.getElementById('view-section');
            const editSection = document.getElementById('edit-section');
            viewSection.style.display = viewSection.style.display === 'none' ? 'block' : 'none';
            editSection.style.display = editSection.style.display === 'none' ? 'block' : 'none';
        }

        function showConfirmPopup() {
            document.getElementById('confirm-modal').style.display = 'flex';
        }

        function hideConfirmPopup() {
            document.getElementById('confirm-modal').style.display = 'none';
        }

        function showSuccessPopup() {
            document.getElementById('success-modal').style.display = 'flex';
        }

        function navigateToViewProfile() {
            document.getElementById('edit-section').style.display = 'none';
            document.getElementById('view-section').style.display = 'block';
            document.getElementById('success-modal').style.display = 'none';
        }

        function navigateToDashboard() {
            window.location.href = "mentor.php"; // Replace with your dashboard file name
        }
    </script>
</head>
<body>
    <div class="profile-container">
        <h2>Mentor Profile</h2>

        <!-- View Profile Section -->
        <div id="view-section">
            <div class="section">
                <h3>Personal Details</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($mentor['name']); ?></p>
            </div>
            <div class="section">
                <h3>Skills</h3>
                <p><?php echo $skills ? htmlspecialchars(implode(', ', $skills)) : 'No skills added yet.'; ?></p>
            </div>
            <div class="section">
                <h3>Completed Projects</h3>
                <?php if (count($projects) > 0): ?>
                    <?php foreach ($projects as $project): ?>
                        <div class="project">
                            <h4><?php echo htmlspecialchars($project['name']); ?></h4>
                            <p><?php echo htmlspecialchars($project['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No completed projects yet.</p>
                <?php endif; ?>
            </div>
            <button class="btn" onclick="toggleEditMode()">Edit Profile</button>
            <button class="btn" onclick="navigateToDashboard()">Back to Dashboard</button>
        </div>

        <!-- Edit Profile Section -->
        <div id="edit-section" style="display: none;">
            <form id="profile-form" action="mentor_profile.php" method="POST" onsubmit="handleFormSubmit(event)">
                <div class="section">
                    <label for="name"><strong>Name:</strong></label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($mentor['name']); ?>" required>
                </div>
                <div class="section">
                    <label for="skills"><strong>Skills:</strong></label>
                    <div id="skills-container">
                        <?php foreach ($skills as $index => $skill): ?>
                            <div class="skill-item">
                                <input type="text" name="skills[]" value="<?php echo htmlspecialchars($skill); ?>" required>
                                <button type="button" onclick="removeSkill(this)">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="addSkill()">Add New Skill</button>
                </div>
                <button type="submit" class="btn">Save Changes</button>
                <button type="button" class="btn" onclick="toggleEditMode()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirm-modal" class="modal">
        <div class="modal-content">
            <p>Are you sure you want to save changes?</p>
            <button class="btn" onclick="confirmSaveChanges()">Yes</button>
            <button class="btn" onclick="hideConfirmPopup()">No</button>
        </div>
    </div>

    <!-- Success Modal -->
    <?php if ($update_success): ?>
    <div id="success-modal" class="modal" style="display: flex;">
        <div class="modal-content">
            <p class="success">Changes updated successfully!</p>
            <button class="btn" onclick="navigateToViewProfile()">Back to Profile</button>
            <button class="btn" onclick="navigateToDashboard()">Back to Dashboard</button>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function addSkill() {
            const skillsContainer = document.getElementById('skills-container');
            const skillItem = document.createElement('div');
            skillItem.classList.add('skill-item');
            skillItem.innerHTML = `
                <input type="text" name="skills[]" required>
                <button type="button" onclick="removeSkill(this)">Remove</button>
            `;
            skillsContainer.appendChild(skillItem);
        }

        function removeSkill(button) {
            const skillItem = button.parentElement;
            skillItem.remove();
        }
    </script>
</body>
</html>
