<?php
require_once "../includes/config.php";require_once "../includes/auth.php";requireRole("counsellor");
$id=currentUserId();$message="";$error="";
if($_SERVER["REQUEST_METHOD"]==="POST"){
    $name=trim($_POST["full_name"]??"");$phone=trim($_POST["phone"]??"");$spec=trim($_POST["specialization"]??"");$bio=trim($_POST["bio"]??"");
    if($name==="")$error="Name is required.";else{
        $conn->begin_transaction();try{
            $stmt=$conn->prepare("UPDATE users SET full_name=?,phone=?,updated_at=NOW() WHERE id=?");$stmt->bind_param("ssi",$name,$phone,$id);$stmt->execute();$stmt->close();
            $stmt=$conn->prepare("INSERT INTO counsellor_profiles(user_id,specialization,bio) VALUES(?,?,?) ON DUPLICATE KEY UPDATE specialization=VALUES(specialization),bio=VALUES(bio)");$stmt->bind_param("iss",$id,$spec,$bio);$stmt->execute();$stmt->close();$conn->commit();$_SESSION["full_name"]=$name;$message="Profile updated.";
        }catch(Exception $e){$conn->rollback();$error="Could not update profile.";}
    }
}
$stmt=$conn->prepare("SELECT u.full_name,u.email,u.phone,cp.specialization,cp.bio FROM users u LEFT JOIN counsellor_profiles cp ON u.id=cp.user_id WHERE u.id=?");$stmt->bind_param("i",$id);$stmt->execute();$user=$stmt->get_result()->fetch_assoc();$stmt->close();
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Counsellor Profile</title><link rel="stylesheet" href="../assets/css/style.css"></head><body><main class="container section"><div class="card"><h1>Counsellor Profile</h1><?php if($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?><?php if($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?><form method="POST"><div class="form-group"><label>Full Name</label><input name="full_name" required value="<?php echo htmlspecialchars($user["full_name"]); ?>"></div><div class="form-group"><label>Email</label><input value="<?php echo htmlspecialchars($user["email"]); ?>" disabled></div><div class="form-group"><label>Phone</label><input name="phone" value="<?php echo htmlspecialchars($user["phone"]??""); ?>"></div><div class="form-group"><label>Specialization</label><input name="specialization" value="<?php echo htmlspecialchars($user["specialization"]??""); ?>"></div><div class="form-group"><label>Bio</label><textarea name="bio"><?php echo htmlspecialchars($user["bio"]??""); ?></textarea></div><button class="btn">Save Profile</button></form><p><a href="dashboard.php">Back</a></p></div></main></body></html>