    <?php
    session_start();
    include $_SERVER['DOCUMENT_ROOT'] . '/projectify/db.php';

    

    

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
    $role = $user['role'];

    // Ensure user is in a group
    if (!$in_group) {
        header("Location: student_not_in_group.php"); // Redirect to page for users not in a group
        exit();
    }

    // Fetch project information only if the user is in a group
    $project_query = "SELECT p.id, p.name, p.description, p.year_and_batch, p.status, p.git_repo_link, p.frozen
                    FROM project p
                    JOIN user_project up ON p.id = up.project_id
                    WHERE up.user_id = '$username'";
    $project_result = $conn->query($project_query);


    // Check if the user is associated with a project
    if ($project_result->num_rows > 0) {
        $project = $project_result->fetch_assoc();
        
        // Ensure the project is frozen before displaying the frozen message
        if ($project['frozen']) {
            // Store project details to display
            $project_name = $project['name'];
            $project_description = $project['description'];
            $year_and_batch = $project['year_and_batch'];
            $project_status = $project['status'];
            $git_repo_link = $project['git_repo_link'];
            $frozen_status = $project['frozen'];
           // Store the project ID in the session
            $_SESSION['project_id'] = $project['id'];
        } else {
            // If project is not frozen, redirect to the in-group page
            header("Location: student_in_group.php");
            exit();
        }
    } else {
        // If no project is found for the user, redirect to an appropriate page or show a message
        echo "You are not associated with any project.";
        exit();
    }

    // Assign task if form is submitted
    if (isset($_POST['assign_task'])) {
        // Get form data
        $task_name = $_POST['task_name'];
        $assigned_to = $_POST['assigned_to'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        // Insert the task into the database
        $task_query = "INSERT INTO task (name, project_id, assigned_to, start, end) VALUES ('$task_name', '{$project['id']}' , '$assigned_to', '$start_date', '$end_date')";
        if ($conn->query($task_query) === TRUE) {
            // Set success flag
            $_SESSION['task_assigned'] = true;

            // Redirect to avoid resubmission on refresh
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error assigning task: " . $conn->error;
        }
    }

    // Display success message if task was assigned
    if (isset($_SESSION['task_assigned'])) {
        echo "<p>Task successfully assigned!</p>";
        unset($_SESSION['task_assigned']); // Clear the session flag
    }

    // Fetch all group members to assign tasks
    $group_members_query = "SELECT u.id, u.name 
                            FROM user u 
                            JOIN user_project up ON u.id = up.user_id
                            WHERE up.project_id = '{$project['id']}'";
    $group_members_result = $conn->query($group_members_query);



    // Fetch tasks specifically assigned to the logged-in user
    $tasks_query = "";
    $tasks_query = "SELECT t.name, t.start, t.end, t.status 
    FROM task t 
    WHERE t.assigned_to = '$username' AND t.project_id = '{$project['id']}'";
    $tasks_result = $conn->query($tasks_query);

    // Prepare the tasks array
    $user_tasks = [];
    while ($task = $tasks_result->fetch_assoc()) {
        $user_tasks[] = $task;
    }







// PDF Upload Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_file'])) {
    // $target_dir = "uploads/";
    $target_dir = $_SERVER['DOCUMENT_ROOT'] . '/projectify/uploads/';

    $original_filename = basename($_FILES["pdf_file"]["name"]);
    $fileType = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

    $project_name = pathinfo($original_filename, PATHINFO_FILENAME);
    $project_id = $_SESSION['project_id'];

    $target_file = $target_dir . $project_id . "." . $fileType;

    if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $target_file)) {
        $python_script = 'py parse_and_generate.py';
        $escaped_target_file = escapeshellarg($target_file);
        $output = shell_exec("$python_script $escaped_target_file 2>&1");

        $warning_position = strpos($output, 'WARNING:');
        if ($warning_position !== false) {
            $output = substr($output, 0, $warning_position);
            $keywords = htmlspecialchars($output);
        }

        $update_query = "UPDATE project SET pdf_path = ?, keywords = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);

        if ($stmt === false) {
            echo "Error preparing update query: " . $conn->error;
            exit;
        }

        $stmt->bind_param("sss", $target_file, $keywords, $project_id);

        if (!$stmt->execute()) {
            echo "Error updating project details: " . $stmt->error;
        }

        $stmt->close();
        $_SESSION['pdf_upload_success'] = true; // Success flag for UI feedback
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit;
    } else {
        echo "Error: There was an issue uploading your file.";
    }
}







include 'navbar.php'; // Include the navbar


    ////////////////////////////////
    ?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Frozen</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($user['name']); ?></h1>
    
    <div class="section-container">
        <h2>Your Project Details (Frozen)</h2>

        <!-- <div class="section-container">
            <h3>Project Dashboard</h3>
            <button onclick="window.location.href = '../project/project.php?id=<?php echo $project['id']; ?>';">
                View Project Dashboard
            </button>
        </div> -->

        <div class="section-container">
            <p><strong>Project Name:</strong> <?php echo htmlspecialchars($project_name); ?></p>
            <p><strong>Project Description:</strong> <?php echo htmlspecialchars($project_description); ?></p>
            <p><strong>Year and Batch:</strong> <?php echo htmlspecialchars($year_and_batch); ?></p>
            <p><strong>Project Status:</strong> <?php echo ucfirst($project_status); ?></p>
            <p><strong>Git Repository Link:</strong> <a href="<?php echo htmlspecialchars($git_repo_link); ?>" target="_blank">View Repository</a></p>
        </div>
        
        <!-- Include the Gantt chart -->
        <?php include 'gantt_chart.php'; ?>
    </div>

    <!-- Task Assignment Form (only visible to group leaders) -->
    <?php if ($role == 'group_leader'): ?>
        <div class="section-container">
            <h3>Assign Task to Group Members</h3>
            <form method="POST" action="">
                <label for="task_name">Task Name:</label>
                <input type="text" id="task_name" name="task_name" required><br>

                <label for="assigned_to">Assign to:</label>
                <select id="assigned_to" name="assigned_to" required>
                    <?php while ($member = $group_members_result->fetch_assoc()): ?>
                        <option value="<?php echo $member['id']; ?>"><?php echo $member['name']; ?></option>
                    <?php endwhile; ?>
                </select><br>

                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" required><br>

                <label for="end_date">End Date:</label>
                <input type="date" id="end_date" name="end_date" required><br>

                <button type="submit" name="assign_task">Assign Task</button>
            </form>
        </div>


<?php
    // Fetch all tasks for the project
    $all_tasks_query = "SELECT t.name, t.start, t.end, t.status, u.name AS assigned_to
                        FROM task t 
                        JOIN user u ON t.assigned_to = u.id
                        WHERE t.project_id = '{$project['id']}'";
    $all_tasks_result = $conn->query($all_tasks_query);
?>




        <div class="section-container">
            <h3>All Group Tasks</h3>
            <?php
                // Fetch all tasks for the project
                $all_tasks_query = "SELECT t.id, t.name, t.start, t.end, t.status, u.name AS assigned_to
                                    FROM task t 
                                    JOIN user u ON t.assigned_to = u.id
                                    WHERE t.project_id = '{$project['id']}'";
                $all_tasks_result = $conn->query($all_tasks_query);
            ?>
            
            <?php if ($all_tasks_result->num_rows > 0): ?>
                <table border="1">
                    <tr>
                        <th>Task Name</th>
                        <th>Assigned To</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Action</th> <!-- New Column for Delete Button -->
                    </tr>
                    <?php while ($task = $all_tasks_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($task['name']); ?></td>
                            <td><?php echo htmlspecialchars($task['assigned_to']); ?></td>
                            <td><?php echo htmlspecialchars($task['start']); ?></td>
                            <td><?php echo htmlspecialchars($task['end']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($task['status'])); ?></td>
                            <td>
                                <form method="POST" action="">
                                    <input type="hidden" name="delete_task_id" value="<?php echo $task['id']; ?>">
                                    <button type="submit" name="delete_task" class="delete-button">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            <?php else: ?>
                <p>No tasks available for this project yet.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php
            // Handle task deletion
            if (isset($_POST['delete_task'])) {
                $delete_task_id = $_POST['delete_task_id'];

                // Sanitize input for security
                $delete_task_id = mysqli_real_escape_string($conn, $delete_task_id);

                // Delete query
                $delete_query = "DELETE FROM task WHERE id = '$delete_task_id'";

                if ($conn->query($delete_query) === TRUE) {
                    echo "<p class='success-message'>Task deleted successfully!</p>";
                    // Optionally refresh the page to reflect the change
                    echo "<meta http-equiv='refresh' content='0'>";
                } else {
                    echo "<p class='error-message'>Error deleting task: " . $conn->error . "</p>";
                }
            }
            ?>

    <!-- Your Assigned Tasks -->
    <div class="section-container">
        <h3>Your Assigned Tasks</h3>
        <?php if (!empty($user_tasks)): ?>
            <table border="1">
                <tr>
                    <th>Task Name</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                </tr>
                <?php foreach ($user_tasks as $task): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($task['name']); ?></td>
                        <td><?php echo htmlspecialchars($task['start']); ?></td>
                        <td><?php echo htmlspecialchars($task['end']); ?></td>
                        <td><?php echo ucfirst(htmlspecialchars($task['status'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>No tasks assigned to you yet.</p>
        <?php endif; ?>
    </div>

    <!-- <div class="section-container">
        <h3>Next Steps:</h3>
        <ul>
            <li>Contact your project leader for further updates.</li>
        </ul>
    </div> -->

    <!-- <div class="section-container">
        <h3>Chat</h3>
        <a href="../chat/chat.php?project_id=<?php echo urlencode($project_id); ?>">
            <button>Go to Chat</button>
        </a>
    </div> -->



    <?php if ($role == 'group_leader'): ?>
    <div class="section-container">
        <h3>Upload a PDF</h3>

        <?php
        if (isset($_SESSION['pdf_upload_success']) && $_SESSION['pdf_upload_success'] === true) {
            echo "<p class='success-message'>File uploaded successfully!</p>";
            unset($_SESSION['pdf_upload_success']);
        }
        ?>

        <form action="" method="post" enctype="multipart/form-data">
            <label for="pdf_file">Select PDF to upload:</label>
            <input type="file" name="pdf_file" id="pdf_file" required>
            <input type="submit" value="Upload PDF">
        </form>
    </div>
<?php endif; ?>


    <a href="../logout.php"><button>Logout</button></a>
</body>
</html>
