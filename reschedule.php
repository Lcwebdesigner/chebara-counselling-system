<?php
require_once "../includes/config.php"; require_once "../includes/auth.php"; requireRole("student");
$id=currentUserId(); $error=""; $message="";
$aid=(int)($_GET["id"]??0);
if(!$aid){header("Location: appointments.php");exit;}
if($_SERVER["REQUEST_METHOD"]==="POST"){
    $stmt=$conn->prepare("UPDATE appointments SET status='reschedule_requested',updated_at=NOW() WHERE id=? AND student_id=? AND status IN('pending','confirmed')"); $stmt->bind_param("ii",$aid,$id); $stmt->execute();
    if($stmt->affected_rows){$message="Reschedule request submitted.";}else{$error="Appointment cannot be rescheduled.";} $stmt->close();
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reschedule Request</title><link rel="stylesheet" href="../assets/css/style.css"></head><body><main class="container section"><div class="card"><h1>Request Reschedule</h1><?php if($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php elseif($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php else: ?><p>Submit a request to your counsellor to reschedule this appointment.</p><form method="POST"><button class="btn">Submit Request</button></form><?php endif; ?><p><a href="appointments.php">Back to appointments</a></p></div></main></body></html>