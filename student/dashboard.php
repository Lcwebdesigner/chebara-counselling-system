<?php

require_once "../includes/config.php";
require_once "../includes/auth.php";

requireRole("student");

$user_id = currentUserId();
$full_name = $_SESSION["full_name"] ?? "Student";

/*
 * Count upcoming appointments for this student.
 */
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM appointments
     WHERE student_id = ?
     AND status IN ('pending', 'confirmed')"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$appointment_count = $result->fetch_assoc()["total"];

$stmt->close();


/*
 * Count available appointment slots.
 */
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM appointment_slots
     WHERE status = 'available'
     AND appointment_date >= CURDATE()"
);

$available_slots = $result->fetch_assoc()["total"];


/*
 * Get recent appointments.
 */
$stmt = $conn->prepare(
    "SELECT
        a.id,
        a.reason,
        a.status,
        a.created_at,
        s.appointment_date,
        s.start_time,
        s.end_time,
        u.full_name AS counsellor_name
     FROM appointments a

     INNER JOIN appointment_slots s
        ON a.slot_id = s.id

     INNER JOIN users u
        ON a.counsellor_id = u.id

     WHERE a.student_id = ?

     ORDER BY s.appointment_date DESC,
              s.start_time DESC

     LIMIT 5"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$appointments = $stmt->get_result();

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Student Dashboard | Chebara TVC</title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>

<body>

<!-- ================= NAVIGATION ================= -->

<nav class="navbar">

    <div class="container">

        <div class="logo">
            Chebara TVC
        </div>

        <div class="nav-links">

            <a href="../index.php">
                Home
            </a>

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="appointments.php">
                My Appointments
            </a>

            <a href="../logout.php">
                Logout
            </a>

        </div>

    </div>

</nav>


<!-- ================= DASHBOARD ================= -->

<main class="container dashboard">

    <div class="card">

        <h1>
            Welcome, <?php echo htmlspecialchars($full_name); ?>
        </h1>

        <p>
            Welcome to your Chebara TVC counselling
            appointment dashboard.
        </p>

    </div>


    <!-- ================= STATISTICS ================= -->

    <div class="cards">

        <div class="stat-card">

            <h3>
                <?php echo $appointment_count; ?>
            </h3>

            <p>
                Active Appointments
            </p>

        </div>


        <div class="stat-card">

            <h3>
                <?php echo $available_slots; ?>
            </h3>

            <p>
                Available Slots
            </p>

        </div>

    </div>


    <!-- ================= QUICK ACTIONS ================= -->

    <div class="card" style="margin-top: 25px;">

        <h2>Quick Actions</h2>

        <br>

        <a
            href="book.php"
            class="btn btn-primary"
        >
            Book Counselling Appointment
        </a>

        <a
            href="appointments.php"
            class="btn btn-secondary"
        >
            View My Appointments
        </a>

    </div>


    <!-- ================= RECENT APPOINTMENTS ================= -->

    <div class="card">

        <h2>Recent Appointments</h2>

        <br>

        <?php if ($appointments->num_rows > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Date
                            </th>

                            <th>
                                Time
                            </th>

                            <th>
                                Counsellor
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php while ($appointment = $appointments->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $appointment["appointment_date"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    date(
                                        "H:i",
                                        strtotime(
                                            $appointment["start_time"]
                                        )
                                    )
                                );
                                ?>
                                -
                                <?php
                                echo htmlspecialchars(
                                    date(
                                        "H:i",
                                        strtotime(
                                            $appointment["end_time"]
                                        )
                                    )
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $appointment["counsellor_name"]
                                );
                                ?>
                            </td>

                            <td>

                                <?php
                                $status =
                                    $appointment["status"];

                                if ($status === "confirmed") {

                                    echo '<span style="color:green;font-weight:bold;">
                                            Confirmed
                                          </span>';

                                } elseif ($status === "pending") {

                                    echo '<span style="color:#d97706;font-weight:bold;">
                                            Pending
                                          </span>';

                                } elseif ($status === "cancelled") {

                                    echo '<span style="color:red;font-weight:bold;">
                                            Cancelled
                                          </span>';

                                } else {

                                    echo htmlspecialchars(
                                        ucfirst(
                                            str_replace(
                                                "_",
                                                " ",
                                                $status
                                            )
                                        )
                                    );
                                }
                                ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p>
                You do not have any appointments yet.
            </p>

            <br>

            <a
                href="book.php"
                class="btn btn-primary"
            >
                Book Your First Appointment
            </a>

        <?php endif; ?>

    </div>

</main>


<!-- ================= FOOTER ================= -->

<footer>

    <div class="container">

        <p>
            &copy; <?php echo date("Y"); ?>
            Chebara Technical and Vocational College
        </p>

        <p>
            Online Counselling Booking System
        </p>

    </div>

</footer>

</body>

</html>
