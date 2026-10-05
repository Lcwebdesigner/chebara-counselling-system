<?php

require_once "../includes/config.php";
require_once "../includes/auth.php";

requireRole("student");

$user_id = currentUserId();

$message = "";
$message_type = "";


/*
 * Handle appointment actions.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $appointment_id = intval($_POST["appointment_id"] ?? 0);
    $action = $_POST["action"] ?? "";

    if ($appointment_id <= 0) {

        $message = "Invalid appointment.";
        $message_type = "danger";

    } else {

        /*
         * Make sure the appointment belongs
         * to the currently logged-in student.
         */
        $stmt = $conn->prepare(
            "SELECT
                a.id,
                a.slot_id,
                a.status,
                s.status AS slot_status
             FROM appointments a
             INNER JOIN appointment_slots s
                ON a.slot_id = s.id
             WHERE a.id = ?
             AND a.student_id = ?
             LIMIT 1"
        );

        $stmt->bind_param(
            "ii",
            $appointment_id,
            $user_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {

            $message = "Appointment not found.";
            $message_type = "danger";

        } else {

            $appointment = $result->fetch_assoc();

            $stmt->close();


            /*
             * CANCEL APPOINTMENT
             */
            if (
                $action === "cancel"
                && in_array(
                    $appointment["status"],
                    ["pending", "confirmed"],
                    true
                )
            ) {

                $conn->begin_transaction();

                try {

                    /*
                     * Cancel appointment.
                     */
                    $new_status = "cancelled";

                    $stmt = $conn->prepare(
                        "UPDATE appointments
                         SET status = ?,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?
                         AND student_id = ?"
                    );

                    $stmt->bind_param(
                        "sii",
                        $new_status,
                        $appointment_id,
                        $user_id
                    );

                    if (!$stmt->execute()) {
                        throw new Exception(
                            "Unable to cancel the appointment."
                        );
                    }

                    $stmt->close();


                    /*
                     * Make the slot available again.
                     */
                    $slot_status = "available";

                    $stmt = $conn->prepare(
                        "UPDATE appointment_slots
                         SET status = ?
                         WHERE id = ?"
                    );

                    $stmt->bind_param(
                        "si",
                        $slot_status,
                        $appointment["slot_id"]
                    );

                    if (!$stmt->execute()) {
                        throw new Exception(
                            "Unable to release the appointment slot."
                        );
                    }

                    $stmt->close();


                    /*
                     * Notification.
                     */
                    $title = "Appointment Cancelled";

                    $notification_message =
                        "Your counselling appointment has been cancelled.";

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
                     * Activity log.
                     */
                    $log_action = "appointment_cancelled";

                    $description =
                        "Student cancelled a counselling appointment.";

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
                        $log_action,
                        $description
                    );

                    $stmt->execute();
                    $stmt->close();


                    $conn->commit();

                    $message =
                        "Your appointment has been cancelled successfully.";
    $message_type = "success";

                } catch (Exception $e) {

                    $conn->rollback();

                    $message = $e->getMessage();
                    $message_type = "danger";
                }


            /*
             * RESCHEDULE REQUEST
             */
            } elseif (
                $action === "reschedule"
                && in_array(
                    $appointment["status"],
                    ["pending", "confirmed"],
                    true
                )
            ) {

                $new_status = "reschedule_requested";

                $stmt = $conn->prepare(
                    "UPDATE appointments
                     SET status = ?,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?
                     AND student_id = ?"
                );

                $stmt->bind_param(
                    "sii",
                    $new_status,
                    $appointment_id,
                    $user_id
                );

                if ($stmt->execute()) {

                    $title = "Reschedule Request";

                    $notification_message =
                        "Your request to reschedule the counselling "
                        . "appointment has been submitted.";

                    $notify = $conn->prepare(
                        "INSERT INTO notifications
                        (
                            user_id,
                            appointment_id,
                            title,
                            message
                        )
                        VALUES (?, ?, ?, ?)"
                    );

                    $notify->bind_param(
                        "iiss",
                        $user_id,
                        $appointment_id,
                        $title,
                        $notification_message
                    );

                    $notify->execute();
                    $notify->close();


                    /*
                     * Activity log.
                     */
                    $log_action = "reschedule_requested";

                    $description =
                        "Student requested counselling appointment rescheduling.";

                    $log = $conn->prepare(
                        "INSERT INTO activity_logs
                        (
                            user_id,
                            action,
                            description
                        )
                        VALUES (?, ?, ?)"
                    );

                    $log->bind_param(
                        "iss",
                        $user_id,
                        $log_action,
                        $description
                    );

                    $log->execute();
                    $log->close();


                    $message =
                        "Your reschedule request has been submitted.";

                    $message_type = "success";

                } else {

                    $message =
                        "Unable to submit the reschedule request.";

                    $message_type = "danger";
                }

                $stmt->close();

            } else {

                $message =
                    "This appointment cannot be modified.";

                $message_type = "danger";
            }
        }

        if ($result->num_rows !== 1 && $stmt) {
            $stmt->close();
        }
    }
}
$query = "
    SELECT
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

    ORDER BY
        s.appointment_date DESC,
        s.start_time DESC
";

$stmt = $conn->prepare($query);

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$appointments = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Appointments | Chebara TVC</title>

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

            <a href="book.php">
                Book Appointment
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


<main class="container dashboard">


    <div class="card">

        <h1>
            My Counselling Appointments
        </h1>

        <p>
            View and manage your counselling
            appointment requests.
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
            Appointment History
        </h2>

        <br>


        <?php if ($appointments->num_rows > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>Date</th>

                            <th>Time</th>

                            <th>Counsellor</th>

                            <th>Reason</th>

                            <th>Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php while (
                        $appointment =
                        $appointments->fetch_assoc()
                    ): ?>

                        <tr>

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
                                    $appointment[
                                        "counsellor_name"
                                    ]
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                if (
                                    !empty(
                                        $appointment["reason"]
                                    )
                                ) {

                                    echo htmlspecialchars(
                                        $appointment["reason"]
                                    );

                                } else {

                                    echo "Not provided";

                                }

                                ?>

                            </td>


                            <td>

                                <?php
 $status =
                                    $appointment["status"];

                                echo htmlspecialchars(
                                    ucwords(
                                        str_replace(
                                            "_",
                                            " ",
                                            $status
                                        )
                                    )
                                );

                                ?>

                            </td>


                            <td>


                                <?php if (
                                    in_array(
                                        $status,
                                        [
                                            "pending",
                                            "confirmed"
                                        ],
                                        true
                                    )
                                ): ?>


                                    <form
                                        method="POST"
                                        action="appointments.php"
                                        style="margin-bottom: 8px;"
                                    >

                                        <input
                                            type="hidden"
                                            name="appointment_id"
                                            value="<?php
                                                echo $appointment["id"];
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="reschedule"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-secondary"
                                        >
                                            Request Reschedule
                                        </button>

                                    </form>


                                    <form
                                        method="POST"
                                        action="appointments.php"
                                        onsubmit="return confirm(
                                            'Are you sure you want to cancel this appointment?'
                                        );"
                                    >

                                        <input
                                            type="hidden"
                                            name="appointment_id"
                                            value="<?php
                                                echo $appointment["id"];
                                            ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="cancel"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger"
                                        >
                                            Cancel
                                        </button>

                                    </form>


                                <?php elseif (
                                    $status ===
                                    "reschedule_requested"
                                ): ?>

                                    <span>
                                        Reschedule requested
                                    </span>


                                <?php elseif (
                                    $status === "completed"
                                ): ?>

                                    <span>
                                        Completed
                                    </span>


                                <?php elseif (
                                    $status === "cancelled"
                                ): ?>

                                    <span>
                                        Cancelled
                                    </span>


                                <?php else: ?>

                                    <span>
                                        No action available
                                    </span>

                                <?php endif; ?>


                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>


        <?php else: ?>

            <div class="alert alert-warning">

                You do not have any counselling
                appointments yet.

                <br><br>

                <a
                    href="book.php"
                    class="btn btn-primary"
                >
                    Book an Appointment
                </a>

            </div>

        <?php endif; ?>

    </div>


    <div class="card">

        <h3>
            Privacy Notice
        </h3>

        <p>
            Your counselling appointment information
            should be kept private. Only provide information
            necessary for appointment scheduling.
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
  
