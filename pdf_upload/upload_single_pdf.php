<?php
session_start();

// Ensure that only mentors or admins can access this page
if ( !isset($_SESSION['username']) || ($_SESSION['role'] === 'student') ) {
    // Redirect to the login page if not logged in as mentor or admin
    header("Location: ../index.php" . "?unauthorized=1");
    exit();
}


include $_SERVER['DOCUMENT_ROOT'] . '/projectify/db.php';

$project_id = $_SESSION['project_id'];  // Use project ID from session


// Ensure project_id exists in the session
if (!isset($_SESSION['project_id'])) {
    echo "Error: Project ID is missing. Please contact your administrator.";
    exit();
}

// Define the directory to store the uploaded PDFs
$target_dir = "uploads/";
$output = "hh";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_file'])) {
    $original_filename = basename($_FILES["pdf_file"]["name"]);
    $fileType = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

    // Handle project_id input
    // $project_id = isset($_POST['project_id']) && !empty($_POST['project_id']) 
    //     ? $_POST['project_id'] 
    //     : uniqid('', true);

    // Extract the base filename (without extension) for the project name
    $project_name = pathinfo($original_filename, PATHINFO_FILENAME);

    $project_id = $_SESSION['project_id'];


    // Create the target file name: {project_id}_{original_filename}
    $target_file = $target_dir . $project_id . "." . $fileType;

    // Move the uploaded file to the target location
    if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $target_file)) {
        // Call the Python script for PDF parsing and keyword generation
        $python_script = 'py parse_and_generate.py'; // Make sure to fix the script name if necessary
        $escaped_target_file = escapeshellarg($target_file); // Escape file path for shell execution

        // Capture both stdout and stderr from the Python script
        $output = shell_exec("$python_script $escaped_target_file 2>&1"); // Redirect stderr to stdout

        echo "Python script output: " . htmlspecialchars($output) . "<br>";

        $warning_position = strpos($output, 'WARNING:');

        if ($warning_position !== false) { // remove excess output
            $output = substr($output, 0, $warning_position);
            $keywords = htmlspecialchars($output);
        }

        // Insert new project entry using the extracted project name
        // $insert_query = "INSERT INTO project (id, name, keywords, pdf_path) 
        //                  VALUES (?, ?, ?, ?)";

        // $stmt = $conn->prepare($insert_query);
        // if ($stmt === false) {
        //     echo "Error preparing insert query: " . $conn->error;
        //     exit;
        // }

        // $stmt->bind_param("ssss", $project_id, $project_name, $keywords, $target_file);

        // if (!$stmt->execute()) {
        //     echo "Error adding project: " . $stmt->error;
        // }

        // Update both PDF path and keywords in the existing project
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
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit;
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
    <title>Upload PDF</title>
    <style>
        /* General reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Body styling */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7fc;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        /* Container styling */
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 100%;
            max-width: 500px;
        }

        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }

        /* Success message styling */
        .success-message {
            color: green;
            text-align: center;
            font-size: 16px;
            margin-bottom: 20px;
        }

        /* Form styling */
        form {
            display: flex;
            flex-direction: column;
        }

        label {
            font-size: 14px;
            color: #555;
            margin-bottom: 8px;
        }

        input[type="text"], input[type="file"] {
            padding: 10px;
            font-size: 14px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
        }

        input[type="submit"] {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        input[type="submit"]:hover {
            background-color: #0056b3;
        }

        input[type="submit"]:active {
            background-color: #003d80;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Upload Project PDF</h2>

        <?php
        // Display success message if redirected after successful upload
        if (isset($_GET['success']) && $_GET['success'] == 1) {
            echo "<p class='success-message'>File uploaded successfully!</p>";
        }
        ?>

        <form action="upload_single_pdf.php" method="post" enctype="multipart/form-data">
            
            <label for="pdf_file">Select PDF to upload:</label>
            <input type="file" name="pdf_file" id="pdf_file" required>

            <input type="submit" value="Upload PDF">
        </form>
    </div>
</body>
</html>
