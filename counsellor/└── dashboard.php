<?php

require_once "../includes/config.php";
require_once "../includes/auth.php";

requireRole("counsellor");

$user_id = currentUserId();


// Get counsellor information
$stmt = $conn->prepare(
    "SELECT full_name, email, phone
     FROM users
     WHERE id = ?
     AND role = 'counsellor'
     LIMIT 1"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user_result = $stmt->get_result();
$counsellor = $user_result->fetch_assoc();

$stmt->close();


// Count available slots
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM appointment_slots
     WHERE counsellor_id = ?
     AND status = 'available'
     AND appointment_date >= CURDATE()"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$available_slots = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


// Count pending appointments
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM appointments
     WHERE counsellor_id = ?
     AND status = 'pending'"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$pending_appointments =
    $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


// Count confirmed appointments
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM appointments
     WHERE counsellor_id = ?
     AND status = 'confirmed'"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$confirmed_appointments =
    $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


// Count reschedule requests
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM appointments
     WHERE counsellor_id = ?
     AND status = 'reschedule_requested'"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$reschedule_requests =
    $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


// Get recent appointments
$stmt = $conn->prepare(
    "SELECT
        a.id,
        a.status,
        a.created_at,
        s.appointment_date,
        s.start_time,
        s.end_time,
        u.full_name AS student_name
     FROM appointments a
     INNER JOIN appointment_slots s
        ON a.slot_id = s.id
     INNER JOIN users u
        ON a.student_id = u.id
     WHERE a.counsellor_id = ?
     ORDER BY
        s.appointment_date DESC,
        s.start_time DESC
     LIMIT 10"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$recent_appointments = $stmt->get_result();

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

    <title>
        Counsellor Dashboard | Chebara TVC
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>

<body>


<nav class="navbar">

    <div class="container">

        <div class="logo">
            Chebara TVC
        </div>

        <div class="nav-links">

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="slots.php">
                Manage Slots
            </a>

            <a href="appointments.php">
                Appointments
            </a>

            <a href="profile.php">
                Profile
            </a>

            <a href="../logout.php">
                Logout
            </a>

        </div>

    </div>

</nav>


<main class="container dashboard">


    <!-- Welcome section -->

    <div class="card">

        <h1>
            Welcome,
            <?php
            echo htmlspecialchars(
                $counsellor["full_name"] ?? "Counsellor"
            );
            ?>
        </h1>

        <p>
            Manage your counselling appointments,
            availability and student requests from
            this dashboard.
        </p>

    </div>


    <!-- Statistics -->

    <div class="dashboard-grid">


        <div class="card">

            <h3>
                Available Slots
            </h3>

            <div class="stat-number">
                <?php
                echo $available_slots;
                ?>
            </div>

            <p>
                Future available slots
            </p>

        </div>


        <div class="card">

            <h3>
                Pending Requests
            </h3>

            <div class="stat-number">
                <?php
                echo $pending_appointments;
                ?>
            </div>

            <p>
                Awaiting confirmation
            </p>

        </div>


        <div class="card">

            <h3>
                Confirmed
            </h3>

            <div class="stat-number">
                <?php
                echo $confirmed_appointments;
                ?>
            </div>

            <p>
                Confirmed appointments
            </p>

        </div>


        <div class="card">

            <h3>
                Reschedule Requests
            </h3>

            <div class="stat-number">
                <?php
                echo $reschedule_requests;
                ?>
            </div>

            <p>
                Requests requiring attention
            </p>

        </div>


    </div>


    <!-- Quick actions -->

    <div class="card">

        <h2>
            Quick Actions
        </h2>

        <br>

        <a
            href="slots.php"
            class="btn btn-primary"
        >
            Manage Appointment Slots
        </a>

        <a
            href="appointments.php"
            class="btn btn-secondary"
        >
            View Appointments
        </a>

        <a
            href="profile.php"
            class="btn btn-secondary"
        >
            My Profile
        </a>

    </div>


    <!-- Recent appointments -->

    <div class="card">

        <h2>
            Recent Appointments
        </h2>

        <br>


        <?php if (
            $recent_appointments->num_rows > 0
        ): ?>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Time
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php while (
                        $appointment =
                        $recent_appointments->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $appointment[
                                        "student_name"
                                    ]
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    date(
                                        "d M Y",
                                        strtotime(
                                            $appointment[
                                                "appointment_date"
                                            ]
                                        )
                                    )
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    date(
                                        "H:i",
                                        strtotime(
                                            $appointment[
                                                "start_time"
                                            ]
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
                                            $appointment[
                                                "end_time"
                                            ]
                                        )
                                    )
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    ucwords(
                                        str_replace(
                                            "_",
                                            " ",
                                            $appointment[
                                                "status"
                                            ]
                                        )
                                    )
                                );

                                ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>


        <?php else: ?>

            <div class="alert alert-warning">

                You currently have no appointments.

            </div>

        <?php endif; ?>


    </div>


    <!-- Counsellor information -->

    <div class="card">

        <h2>
            My Information
        </h2>

        <br>

        <p>
            <strong>Name:</strong>
            <?php
            echo htmlspecialchars(
                $counsellor["full_name"] ?? ""
            );
            ?>
        </p>

        <p>
            <strong>Email:</strong>
            <?php
            echo htmlspecialchars(
                $counsellor["email"] ?? ""
            );
            ?>
        </p>

        <p>
            <strong>Phone:</strong>
            <?php
            echo htmlspecialchars(
                $counsellor["phone"] ?? "Not provided"
            );
            ?>
        </p>

    </div>


</main>


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
