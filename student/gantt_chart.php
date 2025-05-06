<?php
//session_start();
include '../db.php'; // Include the database connection file

// Ensure user is logged in and has a valid session
if (!isset($_SESSION['username'])) {
    echo "You need to be logged in to view the Gantt chart.";
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role']; // Get the user's role from the session

// Fetch user information from the database
$query = "SELECT * FROM user WHERE id = '$username'";
$result = $conn->query($query);

// Check if the user exists
if ($result->num_rows == 0) {
    echo "User not found.";
    exit();
}

$user = $result->fetch_assoc();

// Fetch the project ID the user is part of
$project_query = "SELECT p.id, p.name FROM project p 
                  JOIN user_project up ON p.id = up.project_id 
                  WHERE up.user_id = '$username'";
$project_result = $conn->query($project_query);

// If user is part of a project, fetch tasks for that project
if ($project_result->num_rows > 0) {
    $project = $project_result->fetch_assoc();
    $project_id = $project['id'];

    // Fetch tasks assigned to the project
    $task_query = "SELECT t.name, t.start, t.end, t.status, u.name AS assigned_to 
                   FROM task t 
                   JOIN user u ON t.assigned_to = u.id
                   WHERE t.project_id = '$project_id'";
    $task_result = $conn->query($task_query);

    // Prepare task data for the Gantt chart
    $gantt_data = [];
    while ($task = $task_result->fetch_assoc()) {
        $gantt_data[] = [
            'task_name' => $task['name'],
            'assigned_to' => $task['assigned_to'],
            'start_date' => $task['start'],
            'end_date' => $task['end'],
            'status' => $task['status']
        ];
    }
} else {
    echo "You are not associated with any project.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gantt Chart</title>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <?php if (!empty($gantt_data)): ?>
    <script type="text/javascript">
        google.charts.load('current', {'packages':['gantt']});
        google.charts.setOnLoadCallback(drawGanttChart);

        function drawGanttChart() {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Task ID');
            data.addColumn('string', 'Task Name');
            data.addColumn('string', 'Resource');
            data.addColumn('date', 'Start Date');
            data.addColumn('date', 'End Date');
            data.addColumn('number', 'Duration');
            data.addColumn('number', 'Percent Complete');
            data.addColumn('string', 'Dependencies');

            // Loop through PHP data and add rows to the chart
            <?php foreach ($gantt_data as $key => $task): ?>
                data.addRow([
                    'task<?php echo $key + 1; ?>',
                    '<?php echo $task['task_name']; ?>',
                    '<?php echo $task['assigned_to']; ?>',
                    new Date('<?php echo $task['start_date']; ?>'),
                    new Date('<?php echo $task['end_date']; ?>'),
                    null, 100, null
                ]);
            <?php endforeach; ?>

            var options = {
                height: '100%',
                gantt: {
                    criticalPathEnabled: true,
                    criticalPathStyle: {
                        stroke: '#e64a19',
                        strokeWidth: 5
                    },
                    arrowStyle: {
                        width: 3,
                        color: '#e64a19'
                    }
                }
            };

            var chart = new google.visualization.Gantt(document.getElementById('gantt_chart'));
            chart.draw(data, options);
        }
    </script>
    <?php endif; ?>
</head>
<body>
    <h1>Gantt Chart</h1>
    <?php if (!empty($gantt_data)): ?>
        <div id="gantt_chart"></div>
    <?php else: ?>
        <p>No tasks available to display in the Gantt chart.</p>
    <?php endif; ?>
</body>
</html>

