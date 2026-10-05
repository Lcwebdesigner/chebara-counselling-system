<?php

require_once "../includes/config.php";
require_once "../includes/auth.php";

requireRole("counsellor");

$user_id = currentUserId();

$message = "";
$message_type = "";


/*
 * Handle appointment actions.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $appointment_id =
        intval($_POST["appointment_id"] ?? 0);

    $action =
        $_POST["action"] ?? "";


    if ($appointment_id <= 0) {

        $message = "Invalid appointment.";
        $message_type = "danger";

    } else {

        /*
         * Get appointment and make sure it belongs
         * to this counsellor.
         */
        $stmt = $conn->prepare(
            "SELECT
                a.id,
                a.student_id,
                a.slot_id,
                a.status,
                s.appointment_date,
                s.start_time,
                s.end_time,
                u.full_name AS student_name
             FROM appointments a
             INNER JOIN appointment_slots s
                ON a.slot_id = s.id
             INNER JOIN users u
                ON a.student_id = u.id
             WHERE a.id = ?
             AND a.counsellor_id = ?
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

            $message =
                "Appointment not found.";

            $message_type = "danger";

            $stmt->close();

        } else {

            $appointment =
                $result->fetch_assoc();

            $stmt->close();


            /*
             * CONFIRM APPOINTMENT
             */
            if (
                $action === "confirm"
                && $appointment["status"] === "pending"
            ) {

                $conn->begin_transaction();

                try {

                    $new_status = "confirmed";

                    $stmt = $conn->prepare(
                        "UPDATE appointments
                         SET status = ?,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?
                         AND counsellor_id = ?
                         AND status = 'pending'"
                    );

                    $stmt->bind_param(
                        "sii",
                        $new_status,
                        $appointment_id,
                        $user_id
                    );

                    if (!$stmt->execute()) {

                        throw new Exception(
                            "Unable to confirm appointment."
                        );
                    }

                    if ($stmt->affected_rows !== 1) {

                        throw new Exception(
                            "Appointment could not be confirmed."
                        );
                    }

                    $stmt->close();


                    /*
                     * Notify student.
                     */
                    $title =
                        "Appointment Confirmed";

                    $notification_message =
                        "Your counselling appointment with "
                        . "the counsellor has been confirmed.";

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
                        $appointment["student_id"],
                        $appointment_id,
                        $title,
                        $notification_message
                    );

                    $notify->execute();
                    $notify->close();


                    /*
                     * Activity log.
                     */
                    $log_action =
                        "appointment_confirmed";

                    $description =
                        "Counsellor confirmed a student appointment.";

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


                    $conn->commit();

                    $message =
                        "Appointment confirmed successfully.";

                    $message_type = "success";

                } catch (Exception $e) {

                    $conn->rollback();

                    $message =
                        $e->getMessage();

                    $message_type = "danger";
                }


            /*
             * REJECT APPOINTMENT
             */
            } elseif (
                $action === "reject"
                && in_array(
                    $appointment["status"],
                    ["pending", "reschedule_requested"],
                    true
                )
            ) {

                $conn->begin_transaction();

                try {

                    $new_status = "rejected";

                    $stmt = $conn->prepare(
                        "UPDATE appointments
                         SET status = ?,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?
                         AND counsellor_id = ?"
                    );

                    $stmt->bind_param(
                        "sii",
                        $new_status,
                        $appointment_id,
                        $user_id
                    );

                    if (!$stmt->execute()) {

                        throw new Exception(
                            "Unable to reject appointment."
                        );
                    }

                    $stmt->close();


                    /*
                     * Release the slot.
                     */
                    $slot_status = "available";

                    $stmt = $conn->prepare(
                        "UPDATE appointment_slots
                         SET status = ?
                         WHERE id = ?
                         AND status = 'booked'"
                    );

                    $stmt->bind_param(
                        "si",
                        $slot_status,
                        $appointment["slot_id"]
                    );

                    $stmt->execute();

                    $stmt->close();


                    /*
                     * Notify student.
                     */
                    $title =
                        "Appointment Rejected";

                    $notification_message =
                        "Your counselling appointment request "
                        . "could not be accepted. Please select "
                        . "another available appointment slot.";

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
                        $appointment["student_id"],
                        $appointment_id,
                        $title,
                        $notification_message
                    );

                    $notify->execute();
                    $notify->close();


                    /*
                     * Activity log.
                     */
                    $log_action =
                        "appointment_rejected";

                    $description =
                        "Counsellor rejected a student appointment.";

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


                    $conn->commit();

                    $message =
                        "Appointment rejected successfully.";

                    $message_type = "success";

                } catch (Exception $e) {

                    $conn->rollback();

                    $message =
                        $e->getMessage();

                    $message_type = "danger";
                }


            /*
             * COMPLETE APPOINTMENT
             */
            } elseif (
                $action === "complete"
                && $appointment["status"] === "confirmed"
            ) {

                $new_status = "completed";

                $stmt = $conn->prepare(
                    "UPDATE appointments
                     SET status = ?,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?
                     AND counsellor_id = ?
                     AND status = 'confirmed'"
                );

                $stmt->bind_param(
                    "sii",
                    $new_status,
                    $appointment_id,
                    $user_id
                );


                if ($stmt->execute()) {

                    $title =
                        "Appointment Completed";

                    $notification_message =
                        "Your counselling appointment has been "
                        . "marked as completed.";

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
                        $appointment["student_id"],
                        $appointment_id,
                        $title,
                        $notification_message
                    );

                    $notify->execute();
                    $notify->close();


                    /*
                     * Activity log.
                     */
                    $log_action =
                        "appointment_completed";

                    $description =
                        "Counsellor marked an appointment as completed.";

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
                        "Appointment marked as completed.";

                    $message_type = "success";

                } else {

                    $message =
                        "Unable to complete the appointment.";

                    $message_type = "danger";
                }

                $stmt->close();


            /*
             * HANDLE RESCHEDULE REQUEST
             *
             * For now the request is returned to
             * pending status so the counsellor can
             * review it and confirm/reject it.
             */
            } elseif (
                $action === "review_reschedule"
                && $appointment["status"]
                    === "reschedule_requested"
            ) {

                $new_status = "pending";

                $stmt = $conn->prepare(
                    "UPDATE appointments
                     SET status = ?,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?
                     AND counsellor_id = ?
                     AND status = 'reschedule_requested'"
                );

                $stmt->bind_param(
                    "sii",
                    $new_status,
                    $appointment_id,
                    $user_id
                );


                if ($stmt->execute()) {

                    $title =
                        "Reschedule Request Reviewed";

                    $notification_message =
                        "Your reschedule request has been reviewed. "
                        . "The counsellor will confirm the appointment "
                        . "or provide another available option.";

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
                        $appointment["student_id"],
                        $appointment_id,
                        $title,
                        $notification_message
                    );

                    $notify->execute();
                    $notify->close();


                    $message =
                        "Reschedule request returned for review.";

                    $message_type = "success";

                } else {

                    $message =
                        "Unable to review reschedule request.";

                    $message_type = "danger";
                }

                $stmt->close();

            } else {

                $message =
                    "This appointment action is not available.";

                $message_type = "danger";
            }
        }
    }
}


/*
 * Retrieve counsellor appointments.
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

        u.full_name AS student_name,
        u.email AS student_email,
        u.phone AS student_phone

     FROM appointments a

     INNER JOIN appointment_slots s
        ON a.slot_id = s.id

     INNER JOIN users u
        ON a.student_id = u.id

     WHERE a.counsellor_id = ?

     ORDER BY
        s.appointment_date ASC,
        s.start_time ASC"
);

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$appointments =
    $stmt->get_result();

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
        Appointments | Chebara TVC
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


    <div class="card">

        <h1>
            Counselling Appointments
        </h1>

        <p>
            Review and manage student counselling
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
            Appointment Requests
        </h2>

        <br>


        <?php if ($appointments->num_rows > 0): ?>


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
                                Reason
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php while (
                        $appointment =
                        $appointments->fetch_assoc()
                    ): ?>


                        <tr>


                            <td>

                                <strong>

                                    <?php

                                    echo htmlspecialchars(
                                        $appointment[
                                            "student_name"
                                        ]
                                    );

                                    ?>

                                </strong>

                                <br>

                                <small>

                                    <?php

                                    echo htmlspecialchars(
                                        $appointment[
                                            "student_email"
                                        ]
                                    );

                                    ?>

                                </small>

                                <?php if (
                                    !empty(
                                        $appointment[
                                            "student_phone"
                                        ]
                                    )
                                ): ?>

                                    <br>

                                    <small>

                                        <?php

                                        echo htmlspecialchars(
                                            $appointment[
                                          "student_phone"
                                            ]
                                        );

                                        ?>

                                    </small>

                                <?php endif; ?>

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


                            <td>


                                <?php if (
                                    $appointment["status"]
                                    === "pending"
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
                                            value="confirm"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                        >
                                            Confirm
                                        </button>

                                    </form>


                                    <form
                                        method="POST"
                                        action="appointments.php"
                                        onsubmit="return confirm(
                                            'Are you sure you want to reject this appointment?'
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
                                            value="reject"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger"
                                        >
                                            Reject
                                        </button>

                                    </form>


                                <?php elseif (
                                    $appointment["status"]
                                    === "confirmed"
                                ): ?>


                                    <form
                                        method="POST"
                                        action="appointments.php"
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
                                            value="complete"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                        >
                                            Mark Completed
                                        </button>

                                    </form>


                                <?php elseif (
                                    $appointment["status"]
                                    === "reschedule_requested"
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
                                            value="review_reschedule"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                        >
                                            Review Request
                                        </button>

                                    </form>


                                    <form
                                        method="POST"
                                        action="appointments.php"
                                        onsubmit="return confirm(
                                            'Reject this reschedule request and appointment?'
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
                                            value="reject"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-danger"
                                        >
                                            Reject
                                        </button>

                                    </form>


                                <?php elseif (
                                    $appointment["status"]
                                    === "completed"
                                ): ?>


                                    <span>
                                        Completed
                                    </span>


                                <?php elseif (
                                    $appointment["status"]
                                    === "cancelled"
                                ): ?>


                                    <span>
                                        Cancelled
                                    </span>


                                <?php elseif (
                                    $appointment["status"]
                                    === "rejected"
                                ): ?>


                                    <span>
                                        Rejected
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

                There are currently no student
                appointment requests.

            </div>


        <?php endif; ?>


    </div>


    <div class="card">

        <h3>
            Privacy and Confidentiality
        </h3>

        <p>
            Student counselling information should be
            handled confidentially. Only use the information
            necessary for appointment management.
        </p>

        <p>
            Do not expose counselling records to
            unauthorized users.
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
                                          
