<?php
session_start();
include '../db.php'; // Include the database connection file

// Check if a project ID is provided; if not, redirect to index
$id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$id) {
    header("Location: ../index.php");
    exit();
}

// Fetch project details from the database
$sql_project = "SELECT * FROM project WHERE id = ?";
$stmt = $conn->prepare($sql_project);
$stmt->bind_param("s", $id);
$stmt->execute();
$result = $stmt->get_result();

// Check if the project exists
if ($result->num_rows === 0) {
    echo "Project not found.";
    exit();
}

// Fetch project data
$project = $result->fetch_assoc();
$stmt->close();

// Fetch mentor details
$sql_mentor = "
SELECT 
    p.*,                             -- All project information
    u.id AS mentor_id,               -- Mentor's ID
    u.name AS mentor_name            -- Mentor's name
FROM 
    project p
JOIN 
    user_project up ON p.id = up.project_id
JOIN 
    user u ON up.user_id = u.id
WHERE 
    p.id = ?         
    AND u.role = 'mentor'          
";

$stmt = $conn->prepare($sql_mentor);
$stmt->bind_param("s", $id);
$stmt->execute();
$mentor_result = $stmt->get_result();

if ($mentor_result->num_rows === 0) {
    echo "Mentor not found.";
    exit();
}

$mentor = $mentor_result->fetch_assoc();
$stmt->close();

// Fetch group members
$sql_members = "
SELECT 
    u.id AS member_id,
    u.name AS member_name,
    u.role AS member_role
FROM 
    user_project up
JOIN 
    user u ON (up.user_id = u.id AND (u.role = 'student' OR u.role = 'group_leader')) 
WHERE 
    up.project_id = ?
";

$stmt = $conn->prepare($sql_members);
$stmt->bind_param("s", $id);  // Use the project ID from the URL
$stmt->execute();
$members_result = $stmt->get_result();

// Check if members are found
$members = [];
while ($row = $members_result->fetch_assoc()) {
    $members[] = $row;
}

$stmt->close();


// Fetch status enum values for dropdown (only for admin/mentor)
$status_values = [];
if (isset($_SESSION['role']) && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'mentor')) {
    $status_query = "SHOW COLUMNS FROM project WHERE Field = 'status'";
    $status_result = $conn->query($status_query);
    if ($status_result && $status_row = $status_result->fetch_assoc()) {
        preg_match("/^enum\((.*)\)$/", $status_row['Type'], $matches);
        $status_values = explode(",", $matches[1]);
        $status_values = array_map(function ($val) {
            return trim($val, "'");
        }, $status_values);
    }
}



// Helper function to handle null checks
function safe_value($value, $default = "N/A") {
    return !empty($value) ? htmlspecialchars($value) : $default;
}


        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_SESSION['role']) || !isset($_POST['field']) || !isset($_POST['value']) || !isset($_POST['project_id'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit();
            }

            $role = $_SESSION['role'];
            $field = $_POST['field'];
            $value = $_POST['value'];
            $project_id = (int)$_POST['project_id'];

            // Map fields to allowed roles
            $editable_fields = [
                'group_leader' => ['name', 'git_repo_link', 'description'],
                'admin' => ['status'],
                'mentor' => ['status']
            ];

            if (!in_array($field, $editable_fields[$role] ?? [])) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }

            $sql_update = "UPDATE project SET $field = ? WHERE id = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("si", $value, $project_id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update ' . str_replace('_', ' ', $field)]);
            }

            $stmt->close();
            exit();
        }




////////////////////////////

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Project Details</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Simple styling for the modal */
        #edit-project-modal, #edit-description-modal, #edit-git-repo-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 20px;
            border: 1px solid #ccc;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5);
            z-index: 1000;
            width: 400px;
            border-radius: 8px;
        }

        /* Background overlay for modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        /* General Styles for Light Blue Theme */
        body {
            font-family: 'Arial', sans-serif;
            background: #f7faff; /* Light background with a slight blue tint */
            color: #333; /* Dark text for readability */
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        h1, h2 {
            color: #1e3a8a; /* Deep blue for the title */
            margin-bottom: 20px;
            text-align: center;
            font-size: 28px;
            letter-spacing: 1.5px;
        }

        /* Section styling */
        section {
            margin: 20px 0;
            max-width: 800px;
            width: 100%;
            padding: 15px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Styling for paragraphs and fields */
        p {
            font-size: 16px;
            margin-bottom: 15px;
        }

        strong {
            color: #1e3a8a;
        }

        .edit-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }

        .edit-btn:hover {
            background-color: #45a049;
        }

        /* Form Container for Editing */
        #edit-modal {
            display: none;
            background: #ffffff; /* White background for the form */
            padding: 30px 35px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.1); /* Soft blue shadow */
            max-width: 450px;
            width: 100%;
            margin: 15px auto;
        }

        #edit-modal input[type="text"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #cfe2ff; /* Light blue border */
            border-radius: 5px;
            background: #f0f8ff; /* Very light blue background for inputs */
            color: #333;
            font-size: 14px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        #edit-modal input[type="text"]:focus {
            border-color: #1e3a8a; /* Deep blue border on focus */
            box-shadow: 0 0 5px #1e3a8a; /* Blue glow effect */
            outline: none;
        }

        /* Status Form */
        #status-form select {
            padding: 8px;
            margin: 5px 0;
            border-radius: 5px;
            border: 1px solid #cfe2ff;
        }

        /* Responsive Design */
        @media screen and (max-width: 480px) {
            form {
                padding: 25px;
            }

            input[type="submit"] {
                font-size: 14px;
            }

            h1, h2 {
                font-size: 24px;
            }
        }

        /* Button styling */
        button {
            background-color: #1e3a8a;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }

        button:hover {
            background-color: #2b6cb0;
        }
    </style>
</head>
<body>
    <h1>Project Details</h1>

    <section>
        <p><strong>Name:</strong> <span id="project-name"><?php echo safe_value($project['name']); ?></span></p>
        <p><strong>Description:</strong> <span id="project-description"><?php echo safe_value($project['description']); ?></span></p>
        <p><strong>Git Repo:</strong> <span id="project-git_repo_link"><?php echo safe_value($project['git_repo_link']); ?></span></p>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'group_leader'): ?>
            <button class="edit-btn" data-field="name" data-value="<?php echo safe_value($project['name']); ?>">Edit Name</button>
            <button class="edit-btn" data-field="description" data-value="<?php echo safe_value($project['description']); ?>">Edit Description</button>
            <button class="edit-btn" data-field="git_repo_link" data-value="<?php echo safe_value($project['git_repo_link']); ?>">Edit Git Repo</button>
        <?php endif; ?>
    </section>

    <!-- Universal Modal -->
    <div id="edit-modal">
        <h3 id="modal-title"></h3>
        <form id="edit-form">
            <input type="text" id="edit-input" required>
            <input type="hidden" id="field-name">
            <input type="hidden" id="project-id" value="<?php echo $project['id']; ?>">
            <button type="submit">Save</button>
            <button type="button" id="cancel-modal">Cancel</button>
        </form>
    </div>

    <section>
        <p><strong>Year and Batch:</strong> <?php echo safe_value($project['year_and_batch']); ?></p>
    </section>

    

    <section>
        <p><strong>Status:</strong> 
            <?php if ((isset($_SESSION['role'])) && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'mentor')): ?>
                <form id="status-form" method="POST" action="">
                    <select id="new-status" name="new_status">
                        <?php foreach ($status_values as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo ($project['status'] == $status) ? 'selected' : ''; ?>>
                                <?php echo $status; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" id="save-status">Save</button>
                </form>
            <?php else: ?>
                <?php echo safe_value($project['status']); ?>
            <?php endif; ?>
        </p>
    </section>

    <!-- Include the Gantt chart -->
    <?php include '../student/gantt_chart_external.php'; ?>

    <section>
        <h2>Group Members</h2>
        <ul>
            <?php foreach ($members as $member): ?>
                <li>
                    <strong><?php echo htmlspecialchars($member['member_name']); ?></strong> 
                    <?php if ($member['member_role'] === 'group_leader'): ?>
                        (Leader)
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section>
        <p><strong>Mentor:</strong> <?php echo safe_value($mentor['mentor_name']); ?></p>
    </section>

    <!-- Modal overlay -->
    <div class="modal-overlay"></div>

         
    


    <!--------------------------------------------------------------------------------------------------------------------->
    <script>

document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("edit-modal");
    const title = document.getElementById("modal-title");
    const input = document.getElementById("edit-input");
    const fieldInput = document.getElementById("field-name");
    const projectId = document.getElementById("project-id").value;

    // Open Modal
    document.querySelectorAll(".edit-btn").forEach(button => {
        button.addEventListener("click", function () {
            const field = this.getAttribute("data-field");
            const value = this.getAttribute("data-value");

            title.textContent = `Edit ${field.replace('_', ' ')}`;
            input.value = value;
            fieldInput.value = field;

            modal.style.display = "block";
        });
    });

    // Close Modal
    document.getElementById("cancel-modal").addEventListener("click", function () {
        modal.style.display = "none";
    });

    // Handle Form Submission
    document.getElementById("edit-form").addEventListener("submit", function (e) {
        e.preventDefault();

        const field = fieldInput.value;
        const value = input.value;

        const xhr = new XMLHttpRequest();
        xhr.open("POST", "", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    const elementId = field === 'git_repo_link' ? 'project-git_repo_link' : `project-${field.replace('_', '-')}`;
                    const targetElement = document.querySelector(`#${elementId}`);
                    if (targetElement) {
                        targetElement.textContent = value;
                    } else {
                        console.error(`Element with ID #${elementId} not found.`);
                    }
                    alert(response.message);
                    modal.style.display = "none";
                } else {
                    alert(response.message);
                }
            }
        };

        xhr.send(`field=${encodeURIComponent(field)}&value=${encodeURIComponent(value)}&project_id=${projectId}`);
    });
});



    </script>
</body>
</html>
