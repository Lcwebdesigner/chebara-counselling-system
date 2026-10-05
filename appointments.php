<?php
require_once "../includes/config.php"; require_once "../includes/auth.php"; requireRole("student");
$id=currentUserId(); $message=""; $error="";
if($_SERVER["REQUEST_METHOD"]==="POST"){
    $aid=(int)($_POST["appointment_id"]??0); $action=$_POST["action"]??"";
    $stmt=$conn->prepare("SELECT a.id,a.status,a.slot_id,a.counsellor_id,sl.appointment_date FROM appointments a JOIN appointment_slots sl ON a.slot_id=sl.id WHERE a.id=? AND a.student_id=? LIMIT 1"); $stmt->bind_param("ii",$aid,$id); $stmt->execute(); $a=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if(!$a){$error="Appointment not found.";}
    elseif($action==="cancel" && in_array($a["status"],["pending","confirmed","reschedule_requested"],true)){
        $conn->begin_transaction(); try{
            $stmt=$conn->prepare("UPDATE appointments SET status='cancelled',updated_at=NOW() WHERE id=? AND student_id=?"); $stmt->bind_param("ii",$aid,$id); $stmt->execute(); $stmt->close();
            $stmt=$conn->prepare("UPDATE appointment_slots SET status='available' WHERE id=? AND status='booked'"); $stmt->bind_param("i",$a["slot_id"]); $stmt->execute(); $stmt->close();
            $stmt=$conn->prepare("INSERT INTO notifications(user_id,appointment_id,title,message) VALUES(?,?, 'Appointment cancelled','Your counselling appointment has been cancelled.')"); $stmt->bind_param("ii",$id,$aid); $stmt->execute(); $stmt->close();
            $conn->commit(); $message="Appointment cancelled.";
        }catch(Exception $e){$conn->rollback();$error="Could not cancel appointment.";}
    } elseif($action==="reschedule" && in_array($a["status"],["pending","confirmed"],true)){
        $stmt=$conn->prepare("UPDATE appointments SET status='reschedule_requested',updated_at=NOW() WHERE id=? AND student_id=?"); $stmt->bind_param("ii",$aid,$id); $stmt->execute(); $stmt->close();
        $message="Reschedule request submitted to the counsellor.";
    } else {$error="This appointment cannot be changed in its current status.";}
}
$stmt=$conn->prepare("SELECT a.id,a.status,sl.appointment_date,sl.start_time,sl.end_time,c.full_name counsellor FROM appointments a JOIN appointment_slots sl ON a.slot_id=sl.id JOIN users c ON a.counsellor_id=c.id WHERE a.student_id=? ORDER BY sl.appointment_date DESC,sl.start_time DESC"); $stmt->bind_param("i",$id); $stmt->execute(); $appointments=$stmt->get_result(); $stmt->close();
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My Appointments</title><link rel="stylesheet" href="../assets/css/style.css"></head><body>
<header class="navbar"><div class="container nav-content"><a class="logo" href="../index.php">Chebara Counselling</a><nav class="nav-links"><a href="dashboard.php">Dashboard</a><a href="book.php">Book</a><a href="profile.php">Profile</a><a href="../logout.php">Logout</a></nav></div></header>
<main class="container section"><h1>My Appointments</h1><?php if($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?><?php if($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="card table-responsive"><table><tr><th>Counsellor</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr><?php if($appointments->num_rows): foreach($appointments as $a): ?><tr><td><?php echo htmlspecialchars($a["counsellor"]); ?></td><td><?php echo htmlspecialchars($a["appointment_date"]); ?></td><td><?php echo htmlspecialchars(date("H:i",strtotime($a["start_time"]))." - ".date("H:i",strtotime($a["end_time"]))); ?></td><td><?php echo htmlspecialchars($a["status"]); ?></td><td><?php if(in_array($a["status"],["pending","confirmed"],true)): ?><form method="POST" style="display:inline"><input type="hidden" name="appointment_id" value="<?php echo $a["id"]; ?>"><input type="hidden" name="action" value="reschedule"><button class="btn btn-small btn-secondary">Request Reschedule</button></form> <form method="POST" style="display:inline"><input type="hidden" name="appointment_id" value="<?php echo $a["id"]; ?>"><input type="hidden" name="action" value="cancel"><button class="btn btn-small" data-confirm="Cancel this appointment?">Cancel</button></form><?php else: ?>-<?php endif; ?></td></tr><?php endforeach; else: ?><tr><td colspan="5">No appointments found.</td></tr><?php endif; ?></table></div></main></body></html>