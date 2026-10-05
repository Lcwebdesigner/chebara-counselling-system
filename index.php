<?php
require_once "includes/config.php";
require_once "includes/auth.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chebara Counselling Booking System</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="navbar"><div class="container nav-content">
<a href="index.php" class="logo">Chebara Counselling</a>
<nav class="nav-links">
<a href="index.php">Home</a>
<?php if (isLoggedIn()): ?>
<a href="<?php echo currentUserRole()==='student'?'student/dashboard.php':(currentUserRole()==='counsellor'?'counsellor/dashboard.php':'admin/dashboard.php'); ?>">Dashboard</a>
<a href="notifications.php">Notifications</a>
<a href="logout.php">Logout</a>
<?php else: ?>
<a href="login.php">Login</a><a href="register.php" class="btn btn-small">Register</a>
<?php endif; ?>
</nav></div></header>
<main>
<section class="hero"><div class="container">
<span class="eyebrow">Chebara Technical and Vocational College</span>
<h1>Online Counselling Booking Management System</h1>
<p>Book counselling appointments securely, view available slots and manage appointments online.</p>
<div class="button-group"><a href="register.php" class="btn">Get Started</a><a href="login.php" class="btn btn-secondary">Login</a></div>
</div></section>
<section class="container section">
<h2>How It Works</h2><div class="cards">
<div class="card"><h3>1. Register</h3><p>Create your student account.</p></div>
<div class="card"><h3>2. Choose a Slot</h3><p>View available counsellor appointment slots.</p></div>
<div class="card"><h3>3. Book</h3><p>Submit your appointment request.</p></div>
<div class="card"><h3>4. Get Confirmation</h3><p>Track your appointment status and notifications.</p></div>
</div></section>
<section class="container section"><div class="card">
<h2>About the System</h2>
<p>This system supports counselling appointment scheduling, counsellor slot management, appointment records and administrative oversight.</p>
<p class="muted">The system is an appointment-management tool and does not provide automated diagnosis or replace professional counselling.</p>
</div></section>
</main>
<footer class="footer"><div class="container"><p>&copy; <?php echo date("Y"); ?> Chebara Technical and Vocational College.</p></div></footer>
</body></html>