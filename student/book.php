<?php

require_once "../includes/config.php";
require_once "../includes/auth.php";

requireRole("student");

$user_id = currentUserId();

$message = "";
$message_type = "";

/*
 * Handle appointment booking.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $slot_id = intval($_POST["slot_id"] ?? 0);
    $reason = trim($_POST["reason"] ?? "");

    if ($slot_id <= 0) {

        $message = "Please select a valid appointment slot.";
        $message_type = "danger";

    } else {

        /*
         * Start transaction so that two students
         * cannot successfully book the same slot.
         */
        $conn->begin_transaction();

        try {

            /*
             * Lock the selected slot while checking it.
             */
            $stmt = $conn->prepare(
                "SELECT
                    id,
                    counsellor_id,
                    appointment_date,
                    start_time,
                    end_time,
                    status
                 FROM appointment_slots
                 WHERE id = ?
                 FOR UPDATE"
            );

            $stmt->bind_param("i", $slot_id);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows !== 1) {

                throw new Exception(
                    "The selected appointment slot does not exist."
                );
            }

            $slot = $result->fetch_assoc();

            $stmt->close();


            /*
             * Make sure the slot is still available.
             */
            if ($slot["status"] !== "available") {

                throw new Exception(
                    "Sorry, this appointment slot is no longer available."
                );
            }


            /*
             * Make sure the appointment date has not passed.
             */
            if ($slot["appointment_date"] < date("Y-m-d")) {

                throw new Exception(
                    "This appointment date has already passed."
                );
            }


            /*
             * Check whether this student already has
             * an active appointment.
             */
            $stmt = $conn->prepare(
                "SELECT id
                 FROM appointments
                 WHERE student_id = ?
                 AND status IN ('pending', 'confirmed')
                 AND slot_id = ?
                 LIMIT 1"
            );

            $stmt->bind_param(
                "ii",
                $user_id,
                $slot_id
            );

            $stmt->execute();

            $existing = $stmt->get_result();

            if ($existing->num_rows > 0) {

                throw new Exception(
                    "You have already booked this appointment slot."
                );
            }

            $stmt->close();


            /*
             * Create the appointment.
             */
            $status = "pending";

            $stmt = $conn->prepare(
                "INSERT INTO appointments
                (
                    student_id,
                    counsellor_id,
                    slot_id,
                    reason,
                    status
                )
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "iiiss",
                $user_id,
                $slot["counsellor_id"],
                $slot_id,
                $reason,
                $status
            );

            if (!$stmt->execute()) {

                throw new Exception(
                    "Unable to create the appointment."
                );
            }

            $appointment_id = $stmt->insert_id;

            $stmt->close();


            /*
             * Mark the slot as booked.
             */
            $stmt = $conn->prepare(
                "UPDATE appointment_slots
                 SET status = 'booked'
                 WHERE id = ?
                 AND status = 'available'"
            );

            $stmt->bind_param("i", $slot_id);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {

                throw new Exception(
                    "The appointment slot is no longer available."
                );
            }

            $stmt->close();


            /*
             * Create a notification for the student.
             */
            $title = "Appointment Booking Submitted";

            $notification_message =
                "Your counselling appointment request has been submitted "
                . "and is awaiting confirmation.";

            $stmt = $conn->prepare(
                "INSERT INTO notifications
                (
                    user_id,
                    appointment_id,
                    title,
                    message
                )
                VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "iiss",
                $user_id,
                $appointment_id,
                $title,
                $notification_message
            );

            $stmt->execute();
            $stmt->close();


            /*
             * Record activity.
             */
            $action = "appointment_booked";

            $description =
                "Student submitted a counselling appointment request.";

            $stmt = $conn->prepare(
                "INSERT INTO activity_logs
                (
                    user_id,
                    action,
                    description
                )
                VALUES (?, ?, ?)"
            );

            $stmt->bind_param(
                "iss",
                $user_id,
                $action,
                $description
            );

            $stmt->execute();
            $stmt->close();


            /*
             * Everything succeeded.
             */
            $conn->commit();

            $message =
                "Appointment request submitted successfully.";

            $message_type = "success";

        } catch (Exception $e) {

            $conn->rollback();

            $message = $e->getMessage();
            $message_type = "danger";
        }
    }
}


/*
 * Retrieve available appointment slots.
 *
 * Only future/current dates are shown.
 */
$query = "
    SELECT
        s.id,
        s.appointment_date,
        s.start_time,
        s.end_time,
        u.full_name AS counsellor_name
    FROM appointment_slots s

    INNER JOIN users u
        ON s.counsellor_id = u.id

    WHERE s.status = 'available'
    AND s.appointment_date >= CURDATE()
    AND u.role = 'counsellor'
    AND u.status = 'active'

    ORDER BY
        s.appointment_date ASC,
        s.start_time ASC
";

$slots = $conn->query($query);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Book Appointment | Chebara TVC</title>

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


<!-- ================= BOOKING ================= -->

<main class="container dashboard">

    <div class="card">

        <h1>
            Book a Counselling Appointment
        </h1>

        <p>
            Select an available counselling slot
            and submit your appointment request.
        </p>

    </div>


    <?php if ($message): ?>

        <div class="alert
            <?php
            echo $message_type === "success"
                ? "alert-success"
                : "alert-danger";
            ?>">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <div class="card">

        <h2>
            Available Appointment Slots
        </h2>

        <br>

        <?php if ($slots->num_rows > 0): ?>

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
                                Action
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php while ($slot = $slots->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    date(
                                        "d M Y",
                                        strtotime(
                                            $slot["appointment_date"]
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
                                            $slot["start_time"]
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
                                            $slot["end_time"]
                                        )
                                    )
                                );
                                ?>

                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $slot["counsellor_name"]
                                );
                                ?>
                            </td>

                            <td>

                                <form
                                    method="POST"
                                    action="book.php"
                                >

                                    <input
                                        type="hidden"
                                        name="slot_id"
                                        value="<?php
                                            echo $slot["id"];
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="reason"
                                        value=""
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >
                                        Book
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="alert alert-warning">

                There are currently no available counselling
                appointment slots.

                <br><br>

                Please check again later.

            </div>

        <?php endif; ?>

    </div>


    <!-- ================= PRIVACY NOTE ================= -->

    <div class="card">

        <h3>
            Privacy Notice
        </h3>

        <p>
            Please provide only information necessary for
            appointment scheduling. Do not enter confidential
            counselling-session details in the booking form.
        </p>

    </div>

</main>


<footer>

    <div class="container">

        <p>
            &copy; <?php echo date("Y"); ?>
            Chebara Technical and Vocational College
        </p>

    </div>

</footer>

</body>

</html>
