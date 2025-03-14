<?php
// Start session for login check
session_start();

include $_SERVER['DOCUMENT_ROOT'] . '/projectify/db.php';

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

// Query to get all projects
$sql = "SELECT id, name, description, keywords, year_and_batch, status, git_repo_link, interested_domains, pdf_path FROM project";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Details</title>
</head>
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
<body>
    <h1>Project Details</h1>
    
    <?php
    if ($result->num_rows > 0) {
        // Output data of each row
        while($row = $result->fetch_assoc()) {
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
            echo "<h2>" . htmlspecialchars($row["name"]) . " (ID: " . $project_id . ")</h2>";
            echo "<p><strong>Description:</strong> " . htmlspecialchars($row["description"]) . "</p>";
            echo "<p><strong>Keywords:</strong> " . htmlspecialchars($row["keywords"]) . "</p>";
            echo "<p><strong>Year and Batch:</strong> " . htmlspecialchars($row["year_and_batch"]) . "</p>";
            echo "<p><strong>Status:</strong> " . htmlspecialchars($row["status"]) . "</p>";
            
            if (!empty($row["git_repo_link"])) {
                echo "<p><strong>Git Repository:</strong> <a href='" . htmlspecialchars($row["git_repo_link"]) . "' target='_blank'>" . htmlspecialchars($row["git_repo_link"]) . "</a></p>";
            }
            
            echo "<p><strong>Interested Domains:</strong> " . htmlspecialchars($row["interested_domains"]) . "</p>";
            
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
            if ($comment_result->num_rows > 0) {
                echo "<div class='existing-comments'>";
                while ($comment_row = $comment_result->fetch_assoc()) {
                    echo "<div class='comment'>";
                    echo "<p><strong>" . htmlspecialchars($comment_row["user_id"]) . "</strong> <span style='font-size: 0.8em; color: #666;'>(" . htmlspecialchars($comment_row["formatted_date"]) . ")</span></p>";
                    echo "<p>" . htmlspecialchars($comment_row["comment_text"]) . "</p>";
                    echo "</div>";
                    echo "<hr style='border-top: 1px dashed #ccc;'>";
                }
                echo "</div>";
            } else {
                echo "<p>No comments yet.</p>";
            }
            $comment_stmt->close();
            
            // Comment form
            echo "<form method='post'>";
            echo "<input type='hidden' name='project_id' value='" . $project_id . "'>";
            echo "<textarea name='comment_text' rows='2' cols='50' placeholder='Add your comment here'></textarea><br>";
            echo "<button type='submit' name='submit_comment'>Submit Comment</button>";
            echo "</form>";
            echo "</div>";
            
            echo "<hr>";
            echo "</div>";
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