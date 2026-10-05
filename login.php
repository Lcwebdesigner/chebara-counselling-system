<?php
require_once "includes/config.php";
require_once "includes/auth.php";
if (isLoggedIn()) {
    header("Location: " . (currentUserRole()==='student'?'student/dashboard.php':(currentUserRole()==='counsellor'?'counsellor/dashboard.php':'admin/dashboard.php')));
    exit;
}
$error="";
if ($_SERVER["REQUEST_METHOD"]==="POST") {
    $email=trim($_POST["email"]??"");
    $password=$_POST["password"]??"";
    if ($email==="" || $password==="") $error="Please enter your email and password.";
    else {
        $stmt=$conn->prepare("SELECT id,full_name,email,password,role,status FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s",$email); $stmt->execute(); $user=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$user || !password_verify($password,$user["password"])) $error="Invalid email or password.";
        elseif ($user["status"]!=="active") $error="Your account is not active. Please contact the administrator.";
        else {
            session_regenerate_id(true);
            $_SESSION["user_id"]=$user["id"]; $_SESSION["role"]=$user["role"]; $_SESSION["full_name"]=$user["full_name"];
            header("Location: ".($user["role"]==="student"?"student/dashboard.php":($user["role"]==="counsellor"?"counsellor/dashboard.php":"admin/dashboard.php")));
            exit;
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Login</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><main class="auth-page"><div class="auth-card"><h1>Login</h1><p class="muted">Chebara Counselling System</p>
<?php if($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="POST"><div class="form-group"><label>Email</label><input type="email" name="email" required></div>
<div class="form-group"><label>Password</label><input type="password" name="password" required></div>
<button class="btn" type="submit">Login</button></form>
<p>Don't have an account? <a href="register.php">Register</a></p><p><a href="index.php">Back to home</a></p>
</div></main></body></html>