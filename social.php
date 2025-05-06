<?php
session_start();
include 'db.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'mentor', 'student', 'group_leader'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['username'];
$role = $_SESSION['role'];

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/social/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle post deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_post'])) {
    $post_id = $_POST['post_id'];
    
    // Get the post to check ownership
    $check_stmt = $conn->prepare("SELECT user_id, media_url FROM social_posts WHERE id = ?");
    $check_stmt->bind_param("i", $post_id);
    $check_stmt->execute();
    $post = $check_stmt->get_result()->fetch_assoc();
    
    if ($post && ($post['user_id'] == $user_id || $role == 'admin')) {
        // Delete associated media file if exists
        if ($post['media_url'] && file_exists($post['media_url'])) {
            unlink($post['media_url']);
        }
        
        // Delete all associated data (likes, comments, shares)
        $conn->query("DELETE FROM post_likes WHERE post_id = $post_id");
        $conn->query("DELETE FROM post_comments WHERE post_id = $post_id");
        $conn->query("DELETE FROM post_shares WHERE post_id = $post_id");
        
        // Delete the post itself
        $conn->query("DELETE FROM social_posts WHERE id = $post_id");
        
        // Delete any reposts of this post
        $conn->query("DELETE FROM social_posts WHERE content LIKE '%--- Reposted from Post ID: $post_id ---%'");
    }
    
    header("Location: social.php");
    exit();
}

// Handle comment deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_comment'])) {
    $comment_id = $_POST['comment_id'];
    
    // Get the comment to check ownership
    $check_stmt = $conn->prepare("SELECT user_id FROM post_comments WHERE id = ?");
    $check_stmt->bind_param("i", $comment_id);
    $check_stmt->execute();
    $comment = $check_stmt->get_result()->fetch_assoc();
    
    if ($comment && ($comment['user_id'] == $user_id || $role == 'admin')) {
        $stmt = $conn->prepare("DELETE FROM post_comments WHERE id = ?");
        $stmt->bind_param("i", $comment_id);
        $stmt->execute();
    }
    
    header("Location: social.php");
    exit();
}

// Handle post creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_post'])) {
    $content = trim($_POST['content']);
    $media_url = null;
    
    // Handle file upload
    if (isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
        $file = $_FILES['media'];
        $file_name = time() . '_' . basename($file['name']);
        $file_path = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // Check if file is an actual image/video/PDF
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'pdf', 'doc', 'docx'];
        if (in_array($file_type, $allowed_types)) {
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $media_url = $file_path;
            }
        }
    }
    
    if (!empty($content) || $media_url) {
        $stmt = $conn->prepare("INSERT INTO social_posts (user_id, content, media_url) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $user_id, $content, $media_url);
        $stmt->execute();
        $stmt->close();
        header("Location: social.php");
        exit();
    }
}

// Handle likes
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['like_post'])) {
    $post_id = $_POST['post_id'];
    
    // Check if already liked
    $check_stmt = $conn->prepare("SELECT * FROM post_likes WHERE post_id = ? AND user_id = ?");
    $check_stmt->bind_param("is", $post_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Unlike
        $stmt = $conn->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
        $stmt->bind_param("is", $post_id, $user_id);
    } else {
        // Like
        $stmt = $conn->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("is", $post_id, $user_id);
    }
    $stmt->execute();
    $stmt->close();
    header("Location: social.php");
    exit();
}

// Handle comments
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_comment'])) {
    $post_id = $_POST['post_id'];
    $comment = trim($_POST['comment']);
    
    if (!empty($comment)) {
        $stmt = $conn->prepare("INSERT INTO post_comments (post_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $post_id, $user_id, $comment);
        $stmt->execute();
        $stmt->close();
        header("Location: social.php");
        exit();
    }
}

// Handle reposts
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['repost'])) {
    $post_id = $_POST['post_id'];
    $repost_message = trim($_POST['repost_message']);
    
    // Get the original post and its owner
    $original_post_query = "SELECT sp.*, u.name as original_author FROM social_posts sp JOIN user u ON sp.user_id = u.id WHERE sp.id = ?";
    $stmt = $conn->prepare($original_post_query);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $original_post = $stmt->get_result()->fetch_assoc();
    
    // Create a new post with the reposted content
    $repost_content = $repost_message . "\n\n--- Reposted from " . $original_post['original_author'] . " ---\n" . $original_post['content'] . "\n--- Reposted from Post ID: " . $post_id . " ---";
    $stmt = $conn->prepare("INSERT INTO social_posts (user_id, content, media_url) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $user_id, $repost_content, $original_post['media_url']);
    $stmt->execute();
    
    // Record the share
    $stmt = $conn->prepare("INSERT INTO post_shares (post_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("is", $post_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: social.php");
    exit();
}

// Fetch posts with user details, likes, comments, and shares
$posts_query = "
    SELECT 
        sp.*,
        u.name as user_name,
        u.role as user_role,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = sp.id) as like_count,
        (SELECT COUNT(*) FROM post_comments WHERE post_id = sp.id) as comment_count,
        (SELECT COUNT(*) FROM post_shares WHERE post_id = sp.id) as share_count,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = sp.id AND user_id = ?) as user_liked
    FROM social_posts sp
    JOIN user u ON sp.user_id = u.id
    ORDER BY sp.created_at DESC";

$stmt = $conn->prepare($posts_query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$posts_result = $stmt->get_result();

// Get the appropriate dashboard link based on user role
$dashboard_link = '';
switch ($role) {
    case 'admin':
        $dashboard_link = 'admin.php';
        break;
    case 'mentor':
        $dashboard_link = 'mentor.php';
        break;
    case 'student':
        $dashboard_link = 'student/student_frozen.php';
        break;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Feed - Projectify</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f2f5;
            margin-left: 220px;
        }
        .sidebar {
            width: 220px;
            height: 100vh;
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding-top: 20px;
            position: fixed;
            left: 0;
            top: 0;
            transition: all 0.3s ease-in-out;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
        }
        .sidebar h2 {
            text-align: center;
            font-size: 22px;
            margin-bottom: 20px;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            font-size: 16px;
            transition: background 0.3s ease-in-out;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #2980b9;
            padding-left: 25px;
        }
        .sidebar i {
            margin-right: 10px;
        }
        .social-container {
            max-width: 680px;
            margin: 20px auto;
        }
        .post-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            padding: 15px;
        }
        .post-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }
        .user-info {
            flex-grow: 1;
        }
        .user-name {
            font-weight: 600;
            margin: 0;
        }
        .user-role {
            color: #65676b;
            font-size: 0.9em;
            margin: 0;
        }
        .post-content {
            margin: 15px 0;
        }
        .post-media {
            max-width: 100%;
            border-radius: 8px;
            margin: 10px 0;
        }
        .post-actions {
            display: flex;
            border-top: 1px solid #e4e6eb;
            padding-top: 10px;
        }
        .action-button {
            flex: 1;
            text-align: center;
            padding: 8px;
            color: #65676b;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .action-button:hover {
            background-color: #f0f2f5;
        }
        .action-button.liked {
            color: #1877f2;
        }
        .create-post {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        .comments-section {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e4e6eb;
        }
        .comment {
            display: flex;
            margin-bottom: 10px;
        }
        .comment-content {
            background: #f0f2f5;
            padding: 8px 12px;
            border-radius: 18px;
            flex-grow: 1;
        }
        .comment-form {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .timestamp {
            color: #65676b;
            font-size: 0.9em;
        }
        .file-preview {
            max-width: 200px;
            max-height: 200px;
            margin: 10px 0;
            display: none;
        }
        .delete-post {
            color: #dc3545;
            cursor: pointer;
            float: right;
        }
        .delete-post:hover {
            color: #bd2130;
        }
        .repost-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        .repost-modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
        }
        .close-modal {
            float: right;
            cursor: pointer;
            font-size: 24px;
        }
        .repost-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-style: italic;
        }
        .delete-comment {
            color: #dc3545;
            cursor: pointer;
            float: right;
            font-size: 0.9em;
        }
        .delete-comment:hover {
            color: #bd2130;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Projectify</h2>
        <a href="<?php echo $dashboard_link; ?>"><i class="fas fa-home"></i> Dashboard</a>
        <a href="social.php" class="active"><i class="fas fa-users"></i> Social Feed</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="social-container">
        <div class="create-post">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <textarea class="form-control" name="content" rows="3" placeholder="What's on your mind?" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="media" class="form-label">Upload File (Image, Video, or PDF)</label>
                    <input type="file" class="form-control" id="media" name="media" accept="image/*,video/*,.pdf,.doc,.docx" onchange="previewFile(this)">
                    <img id="preview" class="file-preview" alt="Preview">
                </div>
                <button type="submit" name="create_post" class="btn btn-primary">Post</button>
            </form>
        </div>

        <?php while ($post = $posts_result->fetch_assoc()): ?>
            <div class="post-card">
                <div class="post-header">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <h6 class="user-name"><?php echo htmlspecialchars($post['user_name']); ?></h6>
                        <p class="user-role"><?php echo htmlspecialchars($post['user_role']); ?></p>
                    </div>
                    <span class="timestamp">
                        <?php echo date('M d, Y g:i A', strtotime($post['created_at'])); ?>
                    </span>
                    <?php if ($post['user_id'] == $user_id || $role == 'admin'): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <button type="submit" name="delete_post" class="btn btn-link delete-post">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="post-content">
                    <?php 
                    // Check if this is a repost
                    if (strpos($post['content'], '--- Reposted from') !== false) {
                        echo '<div class="repost-info">';
                        echo htmlspecialchars(explode('---', $post['content'])[1]);
                        echo '</div>';
                        echo nl2br(htmlspecialchars(explode('---', $post['content'])[2]));
                    } else {
                        echo nl2br(htmlspecialchars($post['content']));
                    }
                    ?>
                    <?php if ($post['media_url']): 
                        $file_type = strtolower(pathinfo($post['media_url'], PATHINFO_EXTENSION));
                        if (in_array($file_type, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                            <img src="<?php echo htmlspecialchars($post['media_url']); ?>" class="post-media" alt="Post media">
                        <?php elseif (in_array($file_type, ['mp4'])): ?>
                            <video class="post-media" controls>
                                <source src="<?php echo htmlspecialchars($post['media_url']); ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        <?php elseif (in_array($file_type, ['pdf', 'doc', 'docx'])): ?>
                            <a href="<?php echo htmlspecialchars($post['media_url']); ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-file"></i> View Document
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="post-actions">
                    <form method="POST" class="action-button <?php echo $post['user_liked'] ? 'liked' : ''; ?>">
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                        <button type="submit" name="like_post" class="btn btn-link">
                            <i class="fas fa-thumbs-up"></i> Like (<?php echo $post['like_count']; ?>)
                        </button>
                    </form>
                    <button class="action-button" onclick="toggleComments(<?php echo $post['id']; ?>)">
                        <i class="fas fa-comment"></i> Comment (<?php echo $post['comment_count']; ?>)
                    </button>
                    <button class="action-button" onclick="openRepostModal(<?php echo $post['id']; ?>)">
                        <i class="fas fa-retweet"></i> Repost (<?php echo $post['share_count']; ?>)
                    </button>
                </div>

                <div id="comments-<?php echo $post['id']; ?>" class="comments-section" style="display: none;">
                    <?php
                    $comments_query = "
                        SELECT pc.*, u.name as user_name, u.role as user_role
                        FROM post_comments pc
                        JOIN user u ON pc.user_id = u.id
                        WHERE pc.post_id = ?
                        ORDER BY pc.created_at ASC";
                    $comments_stmt = $conn->prepare($comments_query);
                    $comments_stmt->bind_param("i", $post['id']);
                    $comments_stmt->execute();
                    $comments_result = $comments_stmt->get_result();
                    
                    while ($comment = $comments_result->fetch_assoc()):
                    ?>
                        <div class="comment">
                            <div class="user-avatar" style="width: 32px; height: 32px;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="comment-content">
                                <strong><?php echo htmlspecialchars($comment['user_name']); ?></strong>
                                <span class="timestamp"><?php echo date('M d, Y g:i A', strtotime($comment['created_at'])); ?></span>
                                <?php if ($comment['user_id'] == $user_id || $role == 'admin'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                        <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                        <button type="submit" name="delete_comment" class="btn btn-link delete-comment">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <p class="mb-0"><?php echo htmlspecialchars($comment['content']); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>

                    <form method="POST" class="comment-form">
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                        <input type="text" name="comment" class="form-control" placeholder="Write a comment..." required>
                        <button type="submit" name="add_comment" class="btn btn-primary">Comment</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- Repost Modal -->
    <div id="repostModal" class="repost-modal">
        <div class="repost-modal-content">
            <span class="close-modal" onclick="closeRepostModal()">&times;</span>
            <h3>Repost</h3>
            <form method="POST">
                <input type="hidden" name="post_id" id="repost_post_id">
                <div class="mb-3">
                    <textarea class="form-control" name="repost_message" rows="3" placeholder="Add a message to your repost..."></textarea>
                </div>
                <button type="submit" name="repost" class="btn btn-primary">Repost</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleComments(postId) {
            const commentsSection = document.getElementById(`comments-${postId}`);
            commentsSection.style.display = commentsSection.style.display === 'none' ? 'block' : 'none';
        }

        function previewFile(input) {
            const preview = document.getElementById('preview');
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }

        function openRepostModal(postId) {
            document.getElementById('repostModal').style.display = 'block';
            document.getElementById('repost_post_id').value = postId;
        }

        function closeRepostModal() {
            document.getElementById('repostModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('repostModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html> 