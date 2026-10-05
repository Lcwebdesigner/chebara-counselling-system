<?php
require_once "../includes/config.php";
require_once "../includes/auth.php";

requireRole("admin");

$message = "";
$error = "";

// Update appointment status
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    $appointment_id = (int) $_POST["appointment_id"];
    $new_status = $_POST["status"];

    $allowed_statuses = [
        "pending",
        "confirmed",
        "cancelled",
        "reschedule_requested",
        "completed",
        "rejected"
    ];

    if (!in_array($new_status, $allowed_statuses, true)) {

        $error = "Invalid appointment status.";

    } else {

        $stmt = $conn->prepare("
            UPDATE appointments
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->bind_param("si", $new_status, $appointment_id);

        if ($stmt->execute()) {
            $message = "Appointment status updated successfully.";
        } else {
            $error = "Unable to update appointment.";
        }

        $stmt->close();
    }
}


// Get all appointments
$appointments = $conn->query("
    SELECT
        a.id,
        a.status,
        a.created_at,
        a.updated_at,
        s.full_name AS student_name,
        s.email AS student_email,
        c.full_name AS counsellor_name,
        sl.appointment_date,
        sl.start_time,
        sl.end_time
    FROM appointments a
    INNER JOIN users s
        ON a.student_id = s.id
    INNER JOIN users c
        ON a.counsellor_id = c.id
    INNER JOIN appointment_slots sl
        ON a.slot_id = sl.id
    ORDER BY sl.appointment_date DESC, sl.start_time DESC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Appointments | Chebara Counselling System</title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

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

        <h1>Appointment Management</h1>

        <p>
            View and manage all counselling appointments.
        </p>

    </section>


    <?php if ($message !== ""): ?>

        <div class="alert alert-success">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <section class="card">

        <h2>All Appointments</h2>

        <?php if ($appointments && $appointments->num_rows > 0): ?>

            <div class="table-responsive">

                <table>

                    <thead>

                        <tr>

                            <th>Student</th>
                            <th>Counsellor</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php while ($appointment = $appointments->fetch_assoc()): ?>

                        <tr>

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $appointment["student_name"]
                                );
                                ?>

                                <br>

                                <small>
                                    <?php
                                    echo htmlspecialchars(
                                        $appointment["student_email"]
                                    );
                                    ?>
                                </small>

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
                                echo htmlspecialchars(
                                    date(
                                        "d M Y",
                                        strtotime(
                                            $appointment["appointment_date"]
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
                                            $appointment["start_time"]
                                        )
                                    )
                                    .
                                    " - "
                                    .
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
                                    ucfirst(
                                        str_replace(
                                            "_",
                                            " ",
                                            $appointment["status"]
                                        )
                                    )
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    date(
                                        "d M Y",
                                        strtotime(
                                            $appointment["created_at"]
                                        )
                                    )
                                );
                                ?>

                            </td>


                            <td>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="appointment_id"
                                        value="<?php
                                        echo (int)$appointment["id"];
                                        ?>"
                                    >

                                    <select
                                        name="status"
                                        onchange="this.form.submit()"
                                    >

                                        <option value="pending"
                                            <?php
                                            echo $appointment["status"] === "pending"
                                                ? "selected"
                                                : "";
                                            ?>>
                                            Pending
                                        </option>

                                        <option value="confirmed"
                                            <?php
                                            echo $appointment["status"] === "confirmed"
                                                ? "selected"
                                                : "";
                                            ?>>
                                            Confirmed
                                        </option>

                                        <option value="cancelled"
                                            <?php
                                            echo $appointment["status"] === "cancelled"
                                                ? "selected"
                                                : "";
                                            ?>>
                                            Cancelled
                                        </option>

                                        <option value="reschedule_requested"
                                            <?php
                                            echo $appointment["status"] === "reschedule_requested"
                                                ? "selected"
                                                : "";
                                            ?>>
                                            Reschedule Requested
                                        </option>

                                        <option value="completed"
                                            <?php
                                            echo $appointment["status"] === "completed"
                                                ? "selected"
                                                : "";
                                            ?>>
                                            Completed
                                        </option>

                                        <option value="rejected"
                                            <?php
                                            echo $appointment["status"] === "rejected"
                                                ? "selected"
                                                : "";
                                            ?>>
                                            Rejected
                                        </option>

                                    </select>

                                    <input
                                        type="hidden"
                                        name="update_status"
                                        value="1"
                                    >

                                </form>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <p>
                No appointments have been recorded yet.
            </p>

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
