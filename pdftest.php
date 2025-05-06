<?php
include('db.php');
session_start(); // Start the session

// Define the directory to store the uploaded PDFs
$target_dir = "uploads/";

$keywords = ''; // Variable to store generated keywords

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_file'])) {
    // Extract the file information
    $original_filename = basename($_FILES["pdf_file"]["name"]);
    $fileType = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

    // Check if the uploaded file is a PDF
    if ($fileType != "pdf") {
        echo "Error: Only PDF files are allowed.";
        exit;
    }

    // Check file size (example: max 10MB)
    if ($_FILES["pdf_file"]["size"] > 10485760) {
        echo "Error: File size exceeds the maximum limit of 10MB.";
        exit;
    }

    // Handle project_id input
    $project_id = isset($_POST['project_id']) && !empty($_POST['project_id']) 
        ? $_POST['project_id'] 
        : uniqid('project_', true);

    // Create the target file name: {project_id}_{original_filename}
    $target_file = $target_dir . $project_id . "_" . $original_filename;

    // Move the uploaded file to the target location
    if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $target_file)) {
        echo "The file " . htmlspecialchars($original_filename) . " has been uploaded as " . $target_file . ".<br>";

        // Call Python to extract text from the PDF and generate keywords
        $command = escapeshellcmd("py parse_and_generate.py \"$target_file\"");

        // Execute the command using proc_open for better handling
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w")   // stderr
        );

        $process = proc_open($command, $descriptorspec, $pipes);

        if (is_resource($process)) {
            // Get the output from the Python script
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            // Handle any potential error from stderr
            $error_output = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            // Close the process
            $return_value = proc_close($process);

            // Log output and error for debugging
            echo "Output from Python script: " . htmlspecialchars($output) . "<br>";
            echo "Error from Python script: " . htmlspecialchars($error_output) . "<br>";

            // Check if the Python script executed successfully
            if ($return_value !== 0) {
                echo "Error: Python script execution failed. $error_output";
                exit;
            }

            // Ensure output is not empty and contains the keywords
            if (empty($output)) {
                echo "Error: No keywords generated.";
                exit;
            }

            // Clean and format output (trim any extra spaces or newlines)
            $keywords = trim($output);
            $_SESSION['keywords'] = $keywords; // Store the keywords in the session

            // Check if the project_id already exists
            $check_query = "SELECT id FROM project WHERE id = ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param("s", $project_id);
            $stmt->execute();
            $stmt->store_result();
            $is_update = $stmt->num_rows > 0;
            $stmt->close();

            if ($is_update) {
                // Update existing entry with generated keywords
                $update_query = "UPDATE project SET pdf_path = ?, keywords = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("sss", $target_file, $keywords, $project_id);

                if ($stmt->execute()) {
                    echo "Existing project entry updated successfully.";
                } else {
                    echo "Error updating project: " . $stmt->error;
                }
            } else {
                // Insert new entry with generated keywords
                $insert_query = "INSERT INTO project (id, project_name, description, keywords, year_and_batch, status, git_repo_link, dashboard, frozen, interested_domains, mentor_id, pdf_path) 
                                VALUES (?, 'no', 'no', ?, 'no', 'no', 'no', 'no', 0, 'no', '3', ?)";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("sss", $project_id, $keywords, $target_file);

                if ($stmt->execute()) {
                    echo "New project added successfully.";
                } else {
                    echo "Error adding project: " . $stmt->error;
                }
            }

            $stmt->close();
            // Redirect to prevent form resubmission
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit;
        } else {
            echo "Error: Failed to execute Python script.";
        }
    } else {
        echo "Error: There was an issue uploading your file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Project PDF</title>
</head>
<body>
    <h2>Upload Project PDF</h2>
    <?php
    // Display success message if redirected after successful upload
    if (isset($_GET['success']) && $_GET['success'] == 1) {
        echo "<p style='color: green;'>File uploaded successfully!</p>";
    }

    // Display generated keywords if available
    if (isset($_SESSION['keywords'])) {
        echo "<h3>Generated Keywords:</h3>";
        echo "<p>" . nl2br(htmlspecialchars($_SESSION['keywords'])) . "</p>";
        unset($_SESSION['keywords']); // Clear the session after displaying
    }
    ?>

    <form action="pdftest.php" method="post" enctype="multipart/form-data" id="uploadForm">
        <label for="project_id">Enter Project ID (optional):</label>
        <input type="text" name="project_id" id="project_id"><br><br>
        <label for="pdf_file">Select PDF to upload:</label>
        <input type="file" name="pdf_file" id="pdf_file" required><br><br>
        <input type="submit" value="Upload PDF">
    </form>
</body>
</html>
