<?php
require_once "includes/config.php";
require_once "includes/auth.php";
if (isLoggedIn()) { header("Location: index.php"); exit; }
$errors=[];$success="";
if ($_SERVER["REQUEST_METHOD"]==="POST") {
    $name=trim($_POST["full_name"]??""); $email=trim($_POST["email"]??""); $phone=trim($_POST["phone"]??"");
    $password=$_POST["password"]??""; $confirm=$_POST["confirm_password"]??"";
    if($name==="") $errors[]="Full name is required.";
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]="Enter a valid email.";
    if(strlen($password)<6) $errors[]="Password must be at least 6 characters.";
    if($password!==$confirm) $errors[]="Passwords do not match.";
    if(!$errors){
        $stmt=$conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1"); $stmt->bind_param("s",$email); $stmt->execute(); $exists=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if($exists) $errors[]="An account with that email already exists.";
        else {
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $stmt=$conn->prepare("INSERT INTO users(full_name,email,phone,password,role,status) VALUES(?,?,?,?,'student','active')");
            $stmt->bind_param("ssss",$name,$email,$phone,$hash);
            if($stmt->execute()) $success="Registration successful. You can now log in.";
            else $errors[]="Registration failed. Please try again.";
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Student Registration</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><main class="auth-page"><div class="auth-card"><h1>Student Registration</h1>
<?php foreach($errors as $e): ?><div class="alert alert-error"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
<?php if($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<form method="POST">
<div class="form-group"><label>Full Name</label><input name="full_name" required value="<?php echo htmlspecialchars($_POST["full_name"]??""); ?>"></div>
<div class="form-group"><label>Email</label><input type="email" name="email" required value="<?php echo htmlspecialchars($_POST["email"]??""); ?>"></div>
<div class="form-group"><label>Phone</label><input name="phone" value="<?php echo htmlspecialchars($_POST["phone"]??""); ?>"></div>
<div class="form-group"><label>Password</label><input type="password" name="password" required></div>
<div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
<button class="btn" type="submit">Create Account</button></form>
<p>Already registered? <a href="login.php">Login</a></p></div></main></body></html>