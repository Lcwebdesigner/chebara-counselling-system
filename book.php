<?php
require_once "../includes/config.php"; require_once "../includes/auth.php"; requireRole("student");
$student_id=currentUserId(); $message=""; $error="";
if($_SERVER["REQUEST_METHOD"]==="POST"){
    $slot_id=(int)($_POST["slot_id"]??0);
    if($slot_id<1){$error="Select an appointment slot.";} else {
        $conn->begin_transaction();
        try{
            $stmt=$conn->prepare("SELECT id,counsellor_id,appointment_date,start_time,end_time,status FROM appointment_slots WHERE id=? FOR UPDATE");
            $stmt->bind_param("i",$slot_id); $stmt->execute(); $slot=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if(!$slot || $slot["status"]!=="available" || $slot["appointment_date"]<date("Y-m-d")) throw new Exception("That slot is no longer available.");
            $stmt=$conn->prepare("INSERT INTO appointments(student_id,counsellor_id,slot_id,status) VALUES(?,?,?,'pending')");
            $stmt->bind_param("iii",$student_id,$slot["counsellor_id"],$slot_id); if(!$stmt->execute()) throw new Exception("Could not create appointment."); $appointment_id=$stmt->insert_id; $stmt->close();
            $stmt=$conn->prepare("UPDATE appointment_slots SET status='booked' WHERE id=?"); $stmt->bind_param("i",$slot_id); $stmt->execute(); $stmt->close();
            $title="Appointment booked"; $msg="Your counselling appointment request has been submitted for ".$slot["appointment_date"]." at ".date("H:i",strtotime($slot["start_time"])).".";
            $stmt=$conn->prepare("INSERT INTO notifications(user_id,appointment_id,title,message) VALUES(?,?,?,?)"); $stmt->bind_param("iiss",$student_id,$appointment_id,$title,$msg); $stmt->execute(); $stmt->close();
            $stmt=$conn->prepare("INSERT INTO activity_logs(user_id,action,description) VALUES(?,'appointment_booked',?)"); $stmt->bind_param("is",$student_id,$msg); $stmt->execute(); $stmt->close();
            $conn->commit(); $message="Appointment booked successfully.";
        }catch(Exception $e){$conn->rollback();$error=$e->getMessage();}
    }
}
$slots=$conn->query("SELECT sl.id,sl.appointment_date,sl.start_time,sl.end_time,u.full_name FROM appointment_slots sl JOIN users u ON sl.counsellor_id=u.id WHERE sl.status='available' AND sl.appointment_date>=CURDATE() ORDER BY sl.appointment_date,sl.start_time");
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Book Appointment</title><link rel="stylesheet" href="../assets/css/style.css"></head><body>
<header class="navbar"><div class="container nav-content"><a class="logo" href="../index.php">Chebara Counselling</a><nav class="nav-links"><a href="dashboard.php">Dashboard</a><a href="appointments.php">Appointments</a><a href="../logout.php">Logout</a></nav></div></header>
<main class="container section"><h1>Book Counselling Appointment</h1><?php if($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?><?php if($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="card"><form method="POST"><div class="form-group"><label>Available Slot</label><select name="slot_id" required><option value="">Choose a slot</option><?php foreach($slots as $s): ?><option value="<?php echo $s["id"]; ?>"><?php echo htmlspecialchars($s["appointment_date"]." | ".date("H:i",strtotime($s["start_time"]))." - ".date("H:i",strtotime($s["end_time"]))." | ".$s["full_name"]); ?></option><?php endforeach; ?></select></div><p class="muted">Please avoid entering unnecessary sensitive personal information. The system is for appointment management.</p><button class="btn">Book Appointment</button></form></div></main></body></html>