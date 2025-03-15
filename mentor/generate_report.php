<?php
if (isset($_GET['project_id'])) {
    $project_id = $_GET['project_id'];

    // Run the Python script to generate the PDF
    $command = "python generate_report.py " . escapeshellarg($project_id) . " 2>&1";
    $output = shell_exec($command);

    // Define the PDF filename
    $pdf_filename = "project_report_$project_id.pdf"; 
    $pdf_path = "../reports/" . $pdf_filename; // Correct path to the file

    // Check if the PDF was generated successfully
    if (!file_exists($pdf_path)) {
        die("Error: PDF file not generated!");
    }

    // Clean output buffer before sending headers
    if (ob_get_length()) {
        ob_end_clean();
    }

    // Set headers for PDF download
    header("Content-Type: application/pdf");
    header("Content-Disposition: attachment; filename=\"$pdf_filename\"");
    header("Content-Length: " . filesize($pdf_path));

    // Read and output the file
    readfile($pdf_path);
    exit;
} else {
    echo "Error: Project ID not provided!";
}
?>