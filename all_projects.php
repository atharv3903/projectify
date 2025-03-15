<?php
// Start session for login check
session_start();

include'db.php';


// Save current page URL for redirect after login
$current_page = $_SERVER['PHP_SELF'];
if (!isset($_SESSION['redirect_after_login'])) {
    $_SESSION['redirect_after_login'] = $current_page;
}


// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle like button click
if (isset($_POST['like']) && isset($_POST['project_id'])) {
    $project_id = $_POST['project_id'];

    // Check if user is logged in
    if (isset($_SESSION['username'])) {
        $username = $_SESSION['username'];

        // Check if the user has already liked this project
        $check_like_sql = "SELECT * FROM likes WHERE project_id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_like_sql);
        $check_stmt->bind_param("ss", $project_id, $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // User has already liked this project, so remove the like (unlike)
            $unlike_sql = "DELETE FROM likes WHERE project_id = ? AND user_id = ?";
            $unlike_stmt = $conn->prepare($unlike_sql);
            $unlike_stmt->bind_param("ss", $project_id, $username);
            $unlike_stmt->execute();
            $unlike_stmt->close();
            header("Refresh:0");

        } else {
            // User hasn't liked this project yet, so add the like
            $like_sql = "INSERT INTO likes (project_id, user_id) VALUES (?, ?)";
            $like_stmt = $conn->prepare($like_sql);
            $like_stmt->bind_param("ss", $project_id, $username);
            $like_stmt->execute();
            $like_stmt->close();
            header("Refresh:0");
        }

        $check_stmt->close();
    } else {
        // User is not logged in, redirect to login page
        header("Location: index.php");
        exit();
    }
}

// Handle comment submission
if (isset($_POST['submit_comment']) && isset($_POST['project_id']) && isset($_POST['comment_text'])) {
    $project_id = $_POST['project_id'];
    $comment_text = trim($_POST['comment_text']);

    // Check if user is logged in
    if (isset($_SESSION['username'])) {
        $username = $_SESSION['username'];

        // Only insert comment if text is not empty
        if (!empty($comment_text)) {
            // Insert comment into database
            $comment_sql = "INSERT INTO comment (project_id, user_id, comment_text, timestamp) VALUES (?, ?, ?, NOW())";
            $comment_stmt = $conn->prepare($comment_sql);
            $comment_stmt->bind_param("sss", $project_id, $username, $comment_text);
            $comment_stmt->execute();
            $comment_stmt->close();
        }

        // Refresh the page to show the new comment
        header("Refresh:0");
    } else {
        // User is not logged in, redirect to login page
        header("Location: index.php");
        exit();
    }
}

// Default sorting
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'name';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'asc';
$secondary_sort = isset($_GET['secondary_sort']) ? $_GET['secondary_sort'] : '';
$secondary_order = isset($_GET['secondary_order']) ? $_GET['secondary_order'] : 'asc';

// Validate sort fields to prevent SQL injection
$valid_sort_fields = ['name', 'year_and_batch', 'status', 'interested_domains', 'id'];
if (!in_array($sort_by, $valid_sort_fields)) {
    $sort_by = 'name';
}
if (!in_array($secondary_sort, $valid_sort_fields) && $secondary_sort !== '') {
    $secondary_sort = '';
}

// Validate sort orders
$valid_orders = ['asc', 'desc'];
if (!in_array($sort_order, $valid_orders)) {
    $sort_order = 'asc';
}
if (!in_array($secondary_order, $valid_orders)) {
    $secondary_order = 'asc';
}

// Build the ORDER BY clause
$order_clause = "ORDER BY $sort_by $sort_order";
if (!empty($secondary_sort)) {
    $order_clause .= ", $secondary_sort $secondary_order";
}

// Query to get all projects with sorting
$sql = "SELECT id, name, description, keywords, year_and_batch, status, git_repo_link, interested_domains, pdf_path FROM project $order_clause";
$result = $conn->query($sql);

// Count total number of projects
$count_sql = "SELECT COUNT(*) as total FROM project";
$count_result = $conn->query($count_sql);
$count_row = $count_result->fetch_assoc();
$total_projects = $count_row['total'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Details</title>
    <style>
        .sort-form {
            margin: 20px 0;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }

        .sort-form select,
        .sort-form button {
            padding: 5px;
            margin-right: 10px;
        }

        .sort-container {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .sort-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .comment-section {
            margin-top: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .project {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .current-sort {
            font-weight: bold;
            color: #007bff;
        }
    </style>
    <script>
        // Store the scroll position before reload
        window.onbeforeunload = function () {
            localStorage.setItem("scrollPosition", window.scrollY);
        };

        // Restore scroll position after reload
        window.onload = function () {
            let scrollPosition = localStorage.getItem("scrollPosition");
            if (scrollPosition !== null) {
                window.scrollTo(0, scrollPosition);
            }
        };
    </script>
    <link rel="stylesheet" href="admin/styles.css">
</head>

<body>
    <h1>Project Details</h1>


    
<div class="navbar">    
    <h2>Projectify</h2>
    <a href="index.php">Home</a>
    <a href="all_projects.php">View All Projects</a>
    <!-- <a href="admin/oldPDFUpload.php">Upload (Previous) pdfs</a> -->
    <a href="logout.php">Logout</a>
</div>


    <!-- Sorting form -->
    <div class="sort-form">
        <h3>Sort Projects</h3>
        <form method="get">
            <div class="sort-container">
                <div class="sort-group">
                    <label for="sort_by">Primary Sort:</label>
                    <select name="sort_by" id="sort_by">
                        <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Project Name</option>
                        <option value="year_and_batch" <?php echo $sort_by == 'year_and_batch' ? 'selected' : ''; ?>>Year
                            & Batch</option>
                        <option value="status" <?php echo $sort_by == 'status' ? 'selected' : ''; ?>>Status</option>
                    </select>
                    <select name="sort_order" id="sort_order">
                        <option value="asc" <?php echo $sort_order == 'asc' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="desc" <?php echo $sort_order == 'desc' ? 'selected' : ''; ?>>Descending</option>
                    </select>
                </div>

                <!-- <div class="sort-group">
                    <label for="secondary_sort">Secondary Sort:</label>
                    <select name="secondary_sort" id="secondary_sort">
                        <option value="" <?php echo $secondary_sort == '' ? 'selected' : ''; ?>>None</option>
                        <option value="name" <?php echo $secondary_sort == 'name' ? 'selected' : ''; ?>>Project Name</option>
                        <option value="year_and_batch" <?php echo $secondary_sort == 'year_and_batch' ? 'selected' : ''; ?>>Year & Batch</option>
                        <option value="status" <?php echo $secondary_sort == 'status' ? 'selected' : ''; ?>>Status</option>
                        <option value="interested_domains" <?php echo $secondary_sort == 'interested_domains' ? 'selected' : ''; ?>>Domains</option>
                        <option value="id" <?php echo $secondary_sort == 'id' ? 'selected' : ''; ?>>Project ID</option>
                    </select>
                    <select name="secondary_order" id="secondary_order">
                        <option value="asc" <?php echo $secondary_order == 'asc' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="desc" <?php echo $secondary_order == 'desc' ? 'selected' : ''; ?>>Descending</option>
                    </select>
                </div> -->

                <button type="submit">Apply Sorting</button>
            </div>
        </form>
        <p>Showing <?php echo $result->num_rows; ?> of <?php echo $total_projects; ?> projects</p>
        <p>Current sort:
            <span class="current-sort">
                <?php echo ucfirst($sort_by); ?> (<?php echo $sort_order == 'asc' ? 'Ascending' : 'Descending'; ?>)
                <?php if (!empty($secondary_sort)): ?>
                    , <?php echo ucfirst($secondary_sort); ?>
                    (<?php echo $secondary_order == 'asc' ? 'Ascending' : 'Descending'; ?>)
                <?php endif; ?>
            </span>
        </p>
    </div>

    <?php
    if ($result->num_rows > 0) {
        // Output data of each row
        while ($row = $result->fetch_assoc()) {
            $project_id = $row["id"];

            // Get like count for this project
            $like_count_sql = "SELECT COUNT(*) as like_count FROM likes WHERE project_id = ?";
            $like_stmt = $conn->prepare($like_count_sql);
            $like_stmt->bind_param("s", $project_id);
            $like_stmt->execute();
            $like_result = $like_stmt->get_result();
            $like_row = $like_result->fetch_assoc();
            $like_count = $like_row['like_count'];
            $like_stmt->close();

            // Check if current user has liked this project
            $user_liked = false;
            if (isset($_SESSION['username'])) {
                $username = $_SESSION['username'];
                $user_like_sql = "SELECT * FROM likes WHERE project_id = ? AND user_id = ?";
                $user_like_stmt = $conn->prepare($user_like_sql);
                $user_like_stmt->bind_param("ss", $project_id, $username);
                $user_like_stmt->execute();
                $user_like_result = $user_like_stmt->get_result();
                $user_liked = ($user_like_result->num_rows > 0);
                $user_like_stmt->close();
            }

            echo "<div class='project'>";
            echo "<h2>" . htmlspecialchars($row["name"]) . "</h2>";
            echo "<p><strong>Description:</strong> " . htmlspecialchars($row["description"]) . "</p>";
            echo "<p><strong>Year and Batch:</strong> " . htmlspecialchars($row["year_and_batch"]) . "</p>";
            echo "<p><strong>Status:</strong> " . htmlspecialchars($row["status"]) . "</p>";

            if (!empty($row["git_repo_link"])) {
                echo "<p><strong>Git Repository:</strong> <a href='" . htmlspecialchars($row["git_repo_link"]) . "' target='_blank'>" . htmlspecialchars($row["git_repo_link"]) . "</a></p>";
            }

            if (!empty($row["pdf_path"])) {
                echo "<p><strong>Documentation:</strong> <a href='" . htmlspecialchars($row["pdf_path"]) . "' target='_blank'>View PDF</a></p>";
            }

            // Add like button with count
            echo "<div class='like-section'>";
            echo "<form method='post' style='display:inline;'>";
            echo "<input type='hidden' name='project_id' value='" . $project_id . "'>";
            echo "<button type='submit' name='like'>" . ($user_liked ? "Unlike" : "Like") . "</button>";
            echo " <span>" . $like_count . " likes</span>";
            echo "</form>";
            echo "</div>";

            // Add comment form and display existing comments
            echo "<div class='comment-section'>";
            echo "<h3>Comments</h3>";

            // Get comments for this project
            $comment_sql = "SELECT c.*, DATE_FORMAT(c.timestamp, '%d-%m-%Y %H:%i') as formatted_date FROM comment c WHERE c.project_id = ? ORDER BY c.timestamp DESC";
            $comment_stmt = $conn->prepare($comment_sql);
            $comment_stmt->bind_param("s", $project_id);
            $comment_stmt->execute();
            $comment_result = $comment_stmt->get_result();

            // Display existing comments
            // echo "<div class='existing-comments'>";
            // while ($comment_row = $comment_result->fetch_assoc()) {
            //     echo "<div class='comment'>";
            //     echo "<p><strong>" . htmlspecialchars($comment_row["user_id"]) . "</strong> <span style='font-size: 0.8em; color: #666;'>(" . htmlspecialchars($comment_row["formatted_date"]) . ")</span></p>";
            //     echo "<p>" . htmlspecialchars($comment_row["comment_text"]) . "</p>";
            //     echo "</div>";
            //     echo "<hr style='border-top: 1px dashed #ccc;'>";
            // }
            // echo "</div>";
            // Add button to view comments on a separate page
            echo "<div class='action-buttons'>";
            echo "<a href='project_comments.php?project_id=" . $project_id . "'>View Comments</a>";
            echo "</div>";

            echo "<hr>";
            echo "</div>";
            $comment_stmt->close();
        }
    } else {
        echo "<p>No projects found</p>";
    }

    // Close connection
    $conn->close();
    ?>

    <?php
    // Display login status for demonstration
    if (isset($_SESSION['username'])) {
        echo "<p>You are logged in as: " . $_SESSION['username'] . "</p>";
        echo "<p><a href='logout.php'>Log out</a></p>";
    } else {
        echo "<p>You are not logged in. <a href='index.php'>Log in here</a></p>";
    }
    ?>
</body>

</html>