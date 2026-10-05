<?php
require_once "../includes/config.php";
require_once "../includes/auth.php";

requireRole("admin");

// Get admin information
$user_id = currentUserId();

$stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Statistics
$student_count = 0;
$counsellor_count = 0;
$appointment_count = 0;
$pending_count = 0;
$available_slots = 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'student'");
if ($result) {
    $student_count = $result->fetch_assoc()['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'counsellor'");
if ($result) {
    $counsellor_count = $result->fetch_assoc()['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM appointments");
if ($result) {
    $appointment_count = $result->fetch_assoc()['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE status = 'pending'");
if ($result) {
    $pending_count = $result->fetch_assoc()['total'];
}

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM appointment_slots
    WHERE status = 'available'
    AND appointment_date >= CURDATE()
");
if ($result) {
    $available_slots = $result->fetch_assoc()['total'];
}

// Recent appointments
$recent = $conn->query("
    SELECT
        a.id,
        a.status,
        a.created_at,
        s.full_name AS student_name,
        c.full_name AS counsellor_name,
        sl.appointment_date,
        sl.start_time,
        sl.end_time
    FROM appointments a
    INNER JOIN users s ON a.student_id = s.id
    INNER JOIN users c ON a.counsellor_id = c.id
    INNER JOIN appointment_slots sl ON a.slot_id = sl.id
    ORDER BY a.created_at DESC
    LIMIT 8
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Chebara Counselling System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<header class="navbar">
    <div class="container">
        <div class="nav-content">

            <a href="../index.php" class="logo">
                Chebara Counselling
            </a>

            <nav class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="users.php">Users</a>
                <a href="counsellors.php">Counsellors</a>
                <a href="appointments.php">Appointments</a>
                <a href="reports.php">Reports</a>
                <a href="settings.php">Settings</a>
                <a href="../logout.php">Logout</a>
            </nav>

        </div>
    </div>
</header>

<main class="container">

    <section class="dashboard-header">
        <h1>Admin Dashboard</h1>

        <p>
            Welcome, <?php echo htmlspecialchars($admin['full_name']); ?>.
            Manage the counselling appointment system from here.
        </p>
    </section>

    <!-- Statistics -->

    <section class="dashboard-grid">

        <div class="card stat-card">
            <h3>Students</h3>
            <p class="stat-number">
                <?php echo $student_count; ?>
            </p>
            <a href="users.php">Manage Students</a>
        </div>

        <div class="card stat-card">
            <h3>Counsellors</h3>
            <p class="stat-number">
                <?php echo $counsellor_count; ?>
            </p>
            <a href="counsellors.php">Manage Counsellors</a>
        </div>

        <div class="card stat-card">
            <h3>Total Appointments</h3>
            <p class="stat-number">
                <?php echo $appointment_count; ?>
            </p>
            <a href="appointments.php">View Appointments</a>
        </div>

        <div class="card stat-card">
            <h3>Pending</h3>
            <p class="stat-number">
                <?php echo $pending_count; ?>
            </p>
            <a href="appointments.php">Review Pending</a>
        </div>

        <div class="card stat-card">
            <h3>Available Slots</h3>
            <p class="stat-number">
                <?php echo $available_slots; ?>
            </p>
            <a href="counsellors.php">Manage Slots</a>
        </div>

    </section>

    <!-- Quick Actions -->

    <section class="card">

        <h2>Quick Actions</h2>

        <div class="button-group">

            <a href="users.php" class="btn">
                Manage Users
            </a>

            <a href="counsellors.php" class="btn">
                Manage Counsellors
            </a>

            <a href="appointments.php" class="btn">
                Manage Appointments
            </a>

            <a href="reports.php" class="btn">
                View Reports
            </a>

        </div>

    </section>

    <!-- Recent Appointments -->

    <section class="card">

        <h2>Recent Appointments</h2>

        <?php if ($recent && $recent->num_rows > 0): ?>

            <div class="table-responsive">

                <table>

                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Counsellor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php while ($row = $recent->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php echo htmlspecialchars($row['student_name']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['counsellor_name']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['appointment_date']); ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    date("H:i", strtotime($row['start_time'])) .
                                    " - " .
                                    date("H:i", strtotime($row['end_time']))
                                );
                                ?>
                            </td>

                            <td>
                                <span class="status-badge">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p>No appointments have been recorded yet.</p>

        <?php endif; ?>

    </section>

</main>

<footer class="footer">
    <div class="container">
        <p>
            &copy; <?php echo date("Y"); ?>
            Chebara Technical and Vocational College
            Counselling Booking Management System.
        </p>
    </div>
</footer>

</body>
</html>
