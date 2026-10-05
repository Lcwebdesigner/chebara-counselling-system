<?php
require_once "includes/config.php";
require_once "includes/auth.php";
requireLogin();
$user_id=currentUserId();
if(isset($_GET["read"])){
    $stmt=$conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
    $stmt->bind_param("i",$user_id); $stmt->execute(); $stmt->close();
}
$stmt=$conn->prepare("SELECT id,title,message,is_read,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
$stmt->bind_param("i",$user_id); $stmt->execute(); $notifications=$stmt->get_result(); $stmt->close();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Notifications</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header class="navbar"><div class="container nav-content"><a class="logo" href="index.php">Chebara Counselling</a><nav class="nav-links"><a href="index.php">Home</a><a href="notifications.php">Notifications</a><a href="logout.php">Logout</a></nav></div></header>
<main class="container section"><div class="dashboard-header"><h1>Notifications</h1><a href="?read=1">Mark all as read</a></div>
<?php if($notifications->num_rows): foreach($notifications as $n): ?><div class="card notification <?php echo $n["is_read"]?"":"unread"; ?>"><h3><?php echo htmlspecialchars($n["title"]); ?></h3><p><?php echo nl2br(htmlspecialchars($n["message"])); ?></p><small><?php echo htmlspecialchars($n["created_at"]); ?></small></div><?php endforeach; else: ?><div class="card"><p>No notifications yet.</p></div><?php endif; ?>
</main></body></html>