<?php
//session_start();
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


$user = $result->fetch_assoc();

// If user is part of a project, fetch tasks for that project
if ($result->num_rows > 0) {
    $project_id = $user['id'];

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

