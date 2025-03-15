<?php
// Start session for login check
session_start();

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "projectify";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if project_id is set
if (!isset($_GET['project_id'])) {
    header("Location: all_projects.php");
    exit();
}

$project_id = $_GET['project_id'];

// Get project details
$project_sql = "SELECT name FROM project WHERE id = ?";
$project_stmt = $conn->prepare($project_sql);
$project_stmt->bind_param("s", $project_id);
$project_stmt->execute();
$project_result = $project_stmt->get_result();

if ($project_result->num_rows == 0) {
    // Project not found
    header("Location: all_projects.php");
    exit();
}

$project_row = $project_result->fetch_assoc();
$project_name = $project_row['name'];
$project_stmt->close();

// Handle comment submission
if (isset($_POST['submit_comment']) && isset($_POST['comment_text'])) {
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
        $_SESSION['redirect_after_login'] = "project_comments.php?project_id=" . $project_id;
        header("Location: index.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comments - <?php echo htmlspecialchars($project_name); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f9f9f9;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .comment-form {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        
        .comment-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            margin-bottom: 10px;
        }
        
        .comment-form button {
            padding: 8px 15px;
            background-color: #007bff;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
        }
        
        .comment-form button:hover {
            background-color: #0069d9;
        }
        
        .comments-section {
            margin-top: 20px;
        }
        
        .comment {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .comment:last-child {
            border-bottom: none;
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .comment-author {
            font-weight: bold;
        }
        
        .comment-date {
            color: #666;
            font-size: 0.8em;
        }
        
        .comment-text {
            margin-top: 5px;
        }
        
        .no-comments {
            color: #666;
            font-style: italic;
        }
    </style>
    <script>
        // Store the scroll position before reload
        window.onbeforeunload = function () {
            localStorage.setItem("commentScrollPosition", window.scrollY);
        };

        // Restore scroll position after reload
        window.onload = function () {
            let scrollPosition = localStorage.getItem("commentScrollPosition");
            if (scrollPosition !== null) 
                window.scrollTo(0, scrollPosition);
            }
        };
    </script>
</head>
<body>
    <div class="container">
        <a href="all_projects.php" class="back-link">← Back to Projects</a>
        
        <h1>Comments for: <?php echo htmlspecialchars($project_name); ?></h1>
        
        <!-- Comment form -->
        <div class="comment-form">
            <h3>Add a Comment</h3>
            <?php if (isset($_SESSION['username'])): ?>
                <form method="post">
                    <textarea name="comment_text" rows="3" placeholder="Write your comment here..."></textarea>
                    <button type="submit" name="submit_comment">Post Comment</button>
                </form>
            <?php else: ?>
                <p>You need to <a href="index.php">log in</a> to post a comment.</p>
            <?php endif; ?>
        </div>
        
        <!-- Display comments -->
        <div class="comments-section">
            <h3>Comments</h3>
            
            <?php
            // Get comments for this project
            $comment_sql = "SELECT c.*, DATE_FORMAT(c.timestamp, '%d-%m-%Y %H:%i') as formatted_date FROM comment c WHERE c.project_id = ? ORDER BY c.timestamp DESC";
            $comment_stmt = $conn->prepare($comment_sql);
            $comment_stmt->bind_param("s", $project_id);
            $comment_stmt->execute();
            $comment_result = $comment_stmt->get_result();
            
            if ($comment_result->num_rows > 0) {
                while ($comment_row = $comment_result->fetch_assoc()) {
                    echo "<div class='comment'>";
                    echo "<div class='comment-header'>";
                    echo "<span class='comment-author'>" . htmlspecialchars($comment_row["user_id"]) . "</span>";
                    echo "<span class='comment-date'>" . htmlspecialchars($comment_row["formatted_date"]) . "</span>";
                    echo "</div>";
                    echo "<div class='comment-text'>" . htmlspecialchars($comment_row["comment_text"]) . "</div>";
                    echo "</div>";
                }
            } else {
                echo "<p class='no-comments'>No comments yet. Be the first to comment!</p>";
            }
            $comment_stmt->close();
            ?>
        </div>
    </div>
    
    <?php
    // Close connection
    $conn->close();
    ?>
</body>
</html>